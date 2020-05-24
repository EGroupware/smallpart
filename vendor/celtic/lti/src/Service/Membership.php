<?php

namespace ceLTIc\LTI\Service;

use ceLTIc\LTI;
use ceLTIc\LTI\Context;
use ceLTIc\LTI\ResourceLink;

/**
 * Class to implement the Membership service
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @copyright  SPV Software Products
 * @license  http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3
 */
class Membership extends Service
{

    /**
     * Media type for version 1 of Memberships service.
     */
    const MEMBERSHIPS_MEDIA_TYPE_V1 = 'application/vnd.ims.lis.v2.membershipcontainer+json';

    /**
     * Media type for Names and Role Provisioning service.
     */
    const MEMBERSHIPS_MEDIA_TYPE_NRPS = 'application/vnd.ims.lti-nrps.v2.membershipcontainer+json';

    /**
     * The object to which the memberships apply (ResourceLink or Context).
     *
     * @var Context|ResourceLink $source
     */
    private $source;

    /**
     * Class constructor.
     *
     * @param object       $source     The object to which the memberships apply (ResourceLink or Context)
     * @param string       $endpoint   Service endpoint
     * @param string       $format     Format to request
     */
    public function __construct($source, $endpoint, $format = self::MEMBERSHIPS_MEDIA_TYPE_V1)
    {
        $consumer = $source->getConsumer();
        parent::__construct($consumer, $endpoint, $format);
        $this->source = $source;
    }

    /**
     * Get the memberships.
     *
     * @param string    $role   Role for which memberships are to be requested (optional, default is all roles)
     * @param int       $limit  Limit on the number of memberships to be returned (optional, default is all)
     *
     * @return mixed The array of UserResult objects if successful, otherwise false
     */
    public function get($role = null, $limit = 0)
    {
        $isLink = is_a($this->source, 'ceLTIc\LTI\ResourceLink');
        $parameters = array();
        if (!empty($role)) {
            $parameters['role'] = $role;
        }
        if ($limit > 0) {
            $parameters['limit'] = strval($limit);
        }
        if ($isLink) {
            $parameters['rlid'] = $this->source->getId();
        }
        $http = $this->send('GET', $parameters);
        if (!$http->ok) {
            $userResults = false;
        } else {
            $userResults = array();
            if ($isLink) {
                $oldUsers = $this->source->getUserResultSourcedIDs(true, LTI\ToolProvider::ID_SCOPE_RESOURCE);
            }
            if (isset($http->responseJson->pageOf) && isset($http->responseJson->pageOf->membershipSubject) &&
                isset($http->responseJson->pageOf->membershipSubject->membership)) {
                foreach ($http->responseJson->pageOf->membershipSubject->membership as $membership) {
                    $member = $membership->member;
                    if ($isLink) {
                        $userresult = LTI\UserResult::fromResourceLink($this->source, $member->userId);
                    } else {
                        $userresult = new LTI\UserResult();
                        $userresult->ltiUserId = $member->userId;
                    }

// Set the user name
                    $firstname = (isset($member->givenName)) ? $member->givenName : '';
                    $lastname = (isset($member->familyName)) ? $member->familyName : '';
                    $fullname = (isset($member->name)) ? $member->name : '';
                    $userresult->setNames($firstname, $lastname, $fullname);

// Set the sourcedId
                    if (isset($member->sourcedId)) {
                        $userresult->sourcedId = $member->sourcedId;
                    }

// Set the username
                    if (isset($member->ext_username)) {
                        $userresult->username = $member->ext_username;
                    } elseif (isset($member->ext_user_username)) {
                        $userresult->username = $member->ext_user_username;
                    } elseif (isset($member->custom_username)) {
                        $userresult->username = $member->custom_username;
                    } elseif (isset($member->custom_user_username)) {
                        $userresult->username = $member->custom_user_username;
                    }

// Set the user email
                    $email = (isset($member->email)) ? $member->email : '';
                    $userresult->setEmail($email, $this->source->getConsumer()->defaultEmail);

// Set the user roles
                    if (isset($membership->role)) {
                        $roles = $this->parseContextsInArray($http->responseJson->{'@context'}, $membership->role);
                        $userresult->roles = LTI\ToolProvider::parseRoles($roles, LTI\ToolProvider::LTI_VERSION2);
                    }

// If a result sourcedid is provided save the user
                    if ($isLink) {
                        $doSave = false;
                        if (isset($membership->message)) {
                            foreach ($membership->message as $message) {
                                if (isset($message->message_type) && (($message->message_type === 'basic-lti-launch-request') || ($message->message_type) === 'LtiResourceLinkRequest')) {
                                    if (isset($message->lis_result_sourcedid)) {
                                        $userresult->ltiResultSourcedId = $message->lis_result_sourcedid;
                                        $doSave = true;
                                    }
                                    if (isset($message->ext)) {
                                        if (empty($userresult->username)) {
                                            if (!empty($message->ext->username)) {
                                                $userresult->username = $message->ext->username;
                                            } elseif (!empty($message->ext->user_username)) {
                                                $userresult->username = $message->ext->user_username;
                                            }
                                        }
                                    }
                                    if (isset($message->custom)) {
                                        if (empty($userresult->username)) {
                                            if (!empty($message->custom->username)) {
                                                $userresult->username = $message->custom->username;
                                            } elseif (!empty($message->custom->user_username)) {
                                                $userresult->username = $message->custom->user_username;
                                            }
                                        }
                                    }
                                    break;
                                }
                            }
                        }
                        if (!$doSave && isset($member->resultSourcedId)) {
                            $userresult->setResourceLinkId($this->source->getId());
                            $userresult->ltiResultSourcedId = $member->resultSourcedId;
                            $doSave = true;
                        }
                        if ($doSave) {
                            $userresult->save();
                        }
                    }
                    $userResults[] = $userresult;

// Remove old user (if it exists)
                    if ($isLink) {
                        unset($oldUsers[$userresult->getId(LTI\ToolProvider::ID_SCOPE_RESOURCE)]);
                    }
                }
            } elseif (isset($http->responseJson->members)) {
                foreach ($http->responseJson->members as $member) {
                    if ($isLink) {
                        $userresult = LTI\UserResult::fromResourceLink($this->source, $member->user_id);
                    } else {
                        $userresult = new LTI\UserResult();
                        $userresult->ltiUserId = $member->user_id;
                    }

// Set the user name
                    $firstname = (isset($member->given_name)) ? $member->given_name : '';
                    $lastname = (isset($member->family_name)) ? $member->family_name : '';
                    $fullname = (isset($member->name)) ? $member->name : '';
                    $userresult->setNames($firstname, $lastname, $fullname);

// Set the sourcedId
                    if (isset($member->lis_person_sourcedid)) {
                        $userresult->sourcedId = $member->lis_person_sourcedid;
                    }

// Set the user email
                    $email = (isset($member->email)) ? $member->email : '';
                    $userresult->setEmail($email, $this->source->getConsumer()->defaultEmail);

// Set the user roles
                    if (isset($member->roles)) {
                        $userresult->roles = LTI\ToolProvider::parseRoles($member->roles, LTI\ToolProvider::LTI_VERSION2);
                    }

// If a result sourcedid is provided save the user
                    if ($isLink) {
                        $doSave = false;
                        if (isset($member->message)) {
                            foreach ($member->message as $message) {
                                if (isset($message->{'https://purl.imsglobal.org/spec/lti/claim/message_type'}) && (($message->{'https://purl.imsglobal.org/spec/lti/claim/message_type'} === 'basic-lti-launch-request') || ($message->{'https://purl.imsglobal.org/spec/lti/claim/message_type'}) === 'LtiResourceLinkRequest')) {
                                    if (isset($message->{'https://purl.imsglobal.org/spec/lti-bo/claim/basicoutcome'}) &&
                                        isset($message->{'https://purl.imsglobal.org/spec/lti-bo/claim/basicoutcome'}->lis_result_sourcedid)) {
                                        $userresult->ltiResultSourcedId = $message->{'https://purl.imsglobal.org/spec/lti-bo/claim/basicoutcome'}->lis_result_sourcedid;
                                        $doSave = true;
                                    }
                                    if (isset($message->ext)) {
                                        if (empty($userresult->username)) {
                                            if (!empty($message->ext->username)) {
                                                $userresult->username = $message->ext->username;
                                            } elseif (!empty($message->ext->user_username)) {
                                                $userresult->username = $message->ext->user_username;
                                            }
                                        }
                                    }
                                    if (isset($message->custom)) {
                                        if (empty($userresult->username)) {
                                            if (!empty($message->custom->username)) {
                                                $userresult->username = $message->custom->username;
                                            } elseif (!empty($message->custom->user_username)) {
                                                $userresult->username = $message->custom->user_username;
                                            }
                                        }
                                    }
                                    break;
                                }
                            }
                        }
                        if ($doSave) {
                            $userresult->save();
                        }
                    }
                    $userResults[] = $userresult;

// Remove old user (if it exists)
                    if ($isLink) {
                        unset($oldUsers[$userresult->getId(LTI\ToolProvider::ID_SCOPE_RESOURCE)]);
                    }
                }
            }

/// Delete any old users which were not in the latest list from the tool consumer
            if ($isLink) {
                foreach ($oldUsers as $id => $userresult) {
                    $userresult->delete();
                }
            }
        }

        return $userResults;
    }

}
