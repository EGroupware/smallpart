<?php

namespace ceLTIc\LTI;

/**
 * Class to represent a tool consumer user
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @copyright  SPV Software Products
 * @license  http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3
 */
class User
{

    /**
     * User's first name.
     *
     * @var string $firstname
     */
    public $firstname = '';

    /**
     * User's last name (surname or family name).
     *
     * @var string $lastname
     */
    public $lastname = '';

    /**
     * User's fullname.
     *
     * @var string $fullname
     */
    public $fullname = '';

    /**
     * Allow user name field to be empty?
     *
     * @var bool $allowEmptyName
     */
    public static $allowEmptyName = false;

    /**
     * User's sourcedId.
     *
     * @var string $sourcedId
     */
    public $sourcedId = null;

    /**
     * User's username.
     *
     * @var string $username
     */
    public $username = null;

    /**
     * User's email address.
     *
     * @var string $email
     */
    public $email = '';

    /**
     * User's image URI.
     *
     * @var string $image
     */
    public $image = '';

    /**
     * Roles for user.
     *
     * @var array $roles
     */
    public $roles = array();

    /**
     * Groups for user.
     *
     * @var array $groups
     */
    public $groups = array();

    /**
     * user ID as supplied in the last connection request.
     *
     * @var string|null $ltiUserId
     */
    public $ltiUserId = null;

    /**
     * Class constructor.
     */
    public function __construct()
    {
        $this->initialize();
    }

    /**
     * Initialise the user.
     */
    public function initialize()
    {
        $this->firstname = '';
        $this->lastname = '';
        $this->fullname = '';
        $this->sourcedId = null;
        $this->username = null;
        $this->email = '';
        $this->image = '';
        $this->roles = array();
        $this->groups = array();
    }

    /**
     * Initialise the user.
     *
     * Synonym for initialize().
     */
    public function initialise()
    {
        $this->initialize();
    }

    /**
     * Set the user's name.
     *
     * @param string $firstname User's first name.
     * @param string $lastname User's last name.
     * @param string $fullname User's full name.
     */
    public function setNames($firstname, $lastname, $fullname)
    {
        $names = array(0 => '', 1 => '');
        if (!empty($fullname)) {
            $this->fullname = trim($fullname);
            $names = preg_split("/[\s]+/", $this->fullname, 2);
        }
        if (!empty($firstname)) {
            $this->firstname = trim($firstname);
            $names[0] = $this->firstname;
        } elseif (!empty($names[0])) {
            $this->firstname = $names[0];
        } elseif (!static::$allowEmptyName) {
            $this->firstname = 'User';
        } else {
            $this->firstname = '';
        }
        if (!empty($lastname)) {
            $this->lastname = trim($lastname);
            $names[1] = $this->lastname;
        } elseif (!empty($names[1])) {
            $this->lastname = $names[1];
        } elseif (!static::$allowEmptyName) {
            $this->lastname = $this->ltiUserId;
        } else {
            $this->lastname = '';
        }
        if (empty($this->fullname) && (!empty($this->firstname) || !empty($this->lastname))) {
            $this->fullname = trim("{$this->firstname} {$this->lastname}");
        }
    }

    /**
     * Set the user's email address.
     *
     * @param string $email        Email address value
     * @param string $defaultEmail Value to use if no email is provided (optional, default is none)
     */
    public function setEmail($email, $defaultEmail = null)
    {
        if (!empty($email)) {
            $this->email = $email;
        } elseif (!empty($defaultEmail)) {
            $this->email = $defaultEmail;
            if (substr($this->email, 0, 1) === '@') {
                $this->email = $this->getId() . $this->email;
            }
        } else {
            $this->email = '';
        }
    }

    /**
     * Check if the user is an administrator (at any of the system, institution or context levels).
     *
     * @return bool    True if the user has a role of administrator
     */
    public function isAdmin()
    {
        return $this->hasRole('Administrator') || $this->hasRole('urn:lti:sysrole:ims/lis/SysAdmin') ||
            $this->hasRole('urn:lti:sysrole:ims/lis/Administrator') || $this->hasRole('urn:lti:instrole:ims/lis/Administrator');
    }

    /**
     * Check if the user is staff.
     *
     * @return bool    True if the user has a role of instructor, contentdeveloper or teachingassistant
     */
    public function isStaff()
    {
        return ($this->hasRole('Instructor') || $this->hasRole('ContentDeveloper') || $this->hasRole('TeachingAssistant'));
    }

    /**
     * Check if the user is a learner.
     *
     * @return bool    True if the user has a role of learner
     */
    public function isLearner()
    {
        return $this->hasRole('Learner');
    }

###
###  PRIVATE METHODS
###

    /**
     * Check whether the user has a specified role name.
     *
     * @param string $role Name of role
     *
     * @return bool    True if the user has the specified role
     */
    private function hasRole($role)
    {
        $role2 = null;
        if ((substr($role, 0, 4) !== 'urn:') && (substr($role, 0, 7) !== 'http://') && (substr($role, 0, 8) !== 'https://')) {
            $role2 = 'http://purl.imsglobal.org/vocab/lis/v2/membership#' . $role;
            $role = 'urn:lti:role:ims/lis/' . $role;
        }
        $ok = in_array($role, $this->roles);
        if (!$ok && !empty($role2)) {
            $ok = in_array($role2, $this->roles);
        }

        return $ok;
    }

}
