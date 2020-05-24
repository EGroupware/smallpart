<?php

namespace ceLTIc\LTI\DataConnector;

use ceLTIc\LTI;
use ceLTIc\LTI\ConsumerNonce;
use ceLTIc\LTI\Context;
use ceLTIc\LTI\ResourceLink;
use ceLTIc\LTI\ResourceLinkShare;
use ceLTIc\LTI\ResourceLinkShareKey;
use ceLTIc\LTI\ToolConsumer;
use ceLTIc\LTI\UserResult;

/**
 * Class to represent an LTI Data Connector for Oracle connections
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @copyright  SPV Software Products
 * @license  http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3
 */
###
#    NB This class assumes that an Oracle connection has already been opened to the appropriate schema
###

class DataConnector_oci extends DataConnector
{

    /**
     * Class constructor
     *
     * @param object $db                 Database connection object
     * @param string $dbTableNamePrefix  Prefix for database table names (optional, default is none)
     */
    public function __construct($db, $dbTableNamePrefix = '')
    {
        parent::__construct($db, $dbTableNamePrefix);
        $this->dateFormat = 'd-M-Y';
    }

###
###  ToolConsumer methods
###

    /**
     * Load tool consumer object.
     *
     * @param ToolConsumer $consumer ToolConsumer object
     *
     * @return bool    True if the tool consumer object was successfully loaded
     */
    public function loadToolConsumer($consumer)
    {
        $ok = false;
        if (!is_null($consumer->getRecordId())) {
            $sql = 'SELECT consumer_pk, name, consumer_key256, consumer_key, secret, lti_version, ' .
                'signature_method, consumer_name, consumer_version, consumer_guid, ' .
                'profile, tool_proxy, settings, protected, enabled, ' .
                'enable_from, enable_until, last_access, created, updated ' .
                "FROM {$this->dbTableNamePrefix}" . static::CONSUMER_TABLE_NAME . ' ' .
                'WHERE consumer_pk = :id';
            $query = oci_parse($this->db, $sql);
            $id = $consumer->getRecordId();
            oci_bind_by_name($query, 'id', $id);
        } else {
            $sql = 'SELECT consumer_pk, name, consumer_key256, consumer_key, secret, lti_version, ' .
                'signature_method, consumer_name, consumer_version, consumer_guid, ' .
                'profile, tool_proxy, settings, protected, enabled, ' .
                'enable_from, enable_until, last_access, created, updated ' .
                "FROM {$this->dbTableNamePrefix}" . static::CONSUMER_TABLE_NAME . ' ' .
                'WHERE consumer_key256 = :key256';
            $query = oci_parse($this->db, $sql);
            $key256 = static::getConsumerKey($consumer->getKey());
            oci_bind_by_name($query, 'key256', $key256);
        }

        if (oci_execute($query)) {
            while ($row = oci_fetch_assoc($query)) {
                $row = array_change_key_case($row);
                if (empty($key256) || empty($row['consumer_key']) || ($consumer->getKey() === $row['consumer_key'])) {
                    $consumer->setRecordId(intval($row['consumer_pk']));
                    $consumer->name = $row['name'];
                    $consumer->setkey(empty($row['consumer_key']) ? $row['consumer_key256'] : $row['consumer_key']);
                    $consumer->secret = $row['secret'];
                    $consumer->ltiVersion = $row['lti_version'];
                    $consumer->signatureMethod = $row['signature_method'];
                    $consumer->consumerName = $row['consumer_name'];
                    $consumer->consumerVersion = $row['consumer_version'];
                    $consumer->consumerGuid = $row['consumer_guid'];
                    $consumer->profile = json_decode($row['profile']);
                    $consumer->toolProxy = $row['tool_proxy'];
                    $settingsValue = $row['settings']->load();
                    if (is_string($settingsValue)) {
                        $settings = json_decode($settingsValue, TRUE);
                        if (!is_array($settings)) {
                            $settings = @unserialize($settingsValue);  // check for old serialized setting
                        }
                        if (!is_array($settings)) {
                            $settings = array();
                        }
                    } else {
                        $settings = array();
                    }
                    $consumer->setSettings($settings);
                    $consumer->protected = (intval($row['protected']) === 1);
                    $consumer->enabled = (intval($row['enabled']) === 1);
                    $consumer->enableFrom = null;
                    if (!is_null($row['enable_from'])) {
                        $consumer->enableFrom = strtotime($row['enable_from']);
                    }
                    $consumer->enableUntil = null;
                    if (!is_null($row['enable_until'])) {
                        $consumer->enableUntil = strtotime($row['enable_until']);
                    }
                    $consumer->lastAccess = null;
                    if (!is_null($row['last_access'])) {
                        $consumer->lastAccess = strtotime($row['last_access']);
                    }
                    $consumer->created = strtotime($row['created']);
                    $consumer->updated = strtotime($row['updated']);
                    $ok = true;
                    break;
                }
            }
        }

        return $ok;
    }

    /**
     * Save tool consumer object.
     *
     * @param ToolConsumer $consumer Consumer object
     *
     * @return bool    True if the tool consumer object was successfully saved
     */
    public function saveToolConsumer($consumer)
    {
        $id = $consumer->getRecordId();
        $key = $consumer->getKey();
        $key256 = $this->getConsumerKey($key);
        if ($key === $key256) {
            $key = null;
        }
        $protected = ($consumer->protected) ? 1 : 0;
        $enabled = ($consumer->enabled) ? 1 : 0;
        $profile = (!empty($consumer->profile)) ? json_encode($consumer->profile) : null;
        $settingsValue = json_encode($consumer->getSettings());
        $time = time();
        $now = date("{$this->dateFormat} {$this->timeFormat}", $time);
        $from = null;
        if (!is_null($consumer->enableFrom)) {
            $from = date("{$this->dateFormat} {$this->timeFormat}", $consumer->enableFrom);
        }
        $until = null;
        if (!is_null($consumer->enableUntil)) {
            $until = date("{$this->dateFormat} {$this->timeFormat}", $consumer->enableUntil);
        }
        $last = null;
        if (!is_null($consumer->lastAccess)) {
            $last = date($this->dateFormat, $consumer->lastAccess);
        }
        if (empty($id)) {
            $sql = "INSERT INTO {$this->dbTableNamePrefix}" . static::CONSUMER_TABLE_NAME . ' (consumer_key256, consumer_key, name, ' .
                'secret, lti_version, signature_method, consumer_name, consumer_version, consumer_guid, profile, tool_proxy, settings, protected, enabled, ' .
                'enable_from, enable_until, last_access, created, updated) ' .
                'VALUES (:key256, :key, :name, :secret, :lti_version, :signature_method, :consumer_name, :consumer_version, :consumer_guid, :profile, :tool_proxy, :settings, ' .
                ':protected, :enabled, :enable_from, :enable_until, :last_access, :created, :updated) returning consumer_pk into :pk';
            $query = oci_parse($this->db, $sql);
            oci_bind_by_name($query, 'key256', $key256);
            oci_bind_by_name($query, 'key', $key);
            oci_bind_by_name($query, 'name', $consumer->name);
            oci_bind_by_name($query, 'secret', $consumer->secret);
            oci_bind_by_name($query, 'lti_version', $consumer->ltiVersion);
            oci_bind_by_name($query, 'signature_method', $consumer->signatureMethod);
            oci_bind_by_name($query, 'consumer_name', $consumer->consumerName);
            oci_bind_by_name($query, 'consumer_version', $consumer->consumerVersion);
            oci_bind_by_name($query, 'consumer_guid', $consumer->consumerGuid);
            oci_bind_by_name($query, 'profile', $profile);
            oci_bind_by_name($query, 'tool_proxy', $consumer->toolProxy);
            oci_bind_by_name($query, 'settings', $settingsValue);
            oci_bind_by_name($query, 'protected', $protected);
            oci_bind_by_name($query, 'enabled', $enabled);
            oci_bind_by_name($query, 'enable_from', $from);
            oci_bind_by_name($query, 'enable_until', $until);
            oci_bind_by_name($query, 'last_access', $last);
            oci_bind_by_name($query, 'created', $now);
            oci_bind_by_name($query, 'updated', $now);
            oci_bind_by_name($query, 'pk', $pk);
        } else {
            $sql = 'UPDATE ' . $this->dbTableNamePrefix . static::CONSUMER_TABLE_NAME . ' ' .
                'SET consumer_key256 = :key256, consumer_key = :key, name = :name, secret = :secret, lti_version = :lti_version, ' .
                'signature_method = :signature_method, consumer_name = :consumer_name, ' .
                'consumer_version = :consumer_version, consumer_guid = :consumer_guid, ' .
                'profile = :profile, tool_proxy = :tool_proxy, settings = :settings, ' .
                'protected = :protected, enabled = :enabled, enable_from = :enable_from, enable_until = :enable_until, last_access = :last_access, updated = :updated ' .
                'WHERE consumer_pk = :id';
            $query = oci_parse($this->db, $sql);
            oci_bind_by_name($query, 'key256', $key256);
            oci_bind_by_name($query, 'key', $key);
            oci_bind_by_name($query, 'name', $consumer->name);
            oci_bind_by_name($query, 'secret', $consumer->secret);
            oci_bind_by_name($query, 'lti_version', $consumer->ltiVersion);
            oci_bind_by_name($query, 'signature_method', $consumer->signatureMethod);
            oci_bind_by_name($query, 'consumer_name', $consumer->consumerName);
            oci_bind_by_name($query, 'consumer_version', $consumer->consumerVersion);
            oci_bind_by_name($query, 'consumer_guid', $consumer->consumerGuid);
            oci_bind_by_name($query, 'profile', $profile);
            oci_bind_by_name($query, 'tool_proxy', $consumer->toolProxy);
            oci_bind_by_name($query, 'settings', $settingsValue);
            oci_bind_by_name($query, 'protected', $protected);
            oci_bind_by_name($query, 'enabled', $enabled);
            oci_bind_by_name($query, 'enable_from', $from);
            oci_bind_by_name($query, 'enable_until', $until);
            oci_bind_by_name($query, 'last_access', $last);
            oci_bind_by_name($query, 'updated', $now);
            oci_bind_by_name($query, 'id', $id);
        }
        $ok = oci_execute($query);
        if ($ok) {
            if (empty($id)) {
                $consumer->setRecordId(intval($pk));
                $consumer->created = $time;
            }
            $consumer->updated = $time;
        }

        return $ok;
    }

    /**
     * Delete tool consumer object.
     *
     * @param ToolConsumer $consumer Consumer object
     *
     * @return bool    True if the tool consumer object was successfully deleted
     */
    public function deleteToolConsumer($consumer)
    {
        $id = $consumer->getRecordId();

// Delete any nonce values for this consumer
        $sql = "DELETE FROM {$this->dbTableNamePrefix}" . static::NONCE_TABLE_NAME . ' WHERE consumer_pk = :id';
        $query = oci_parse($this->db, $sql);
        oci_bind_by_name($query, 'id', $id);
        oci_execute($query);

// Delete any outstanding share keys for resource links for this consumer
        $sql = "DELETE FROM {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . ' ' .
            "WHERE resource_link_pk IN (SELECT resource_link_pk FROM {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_TABLE_NAME . ' ' .
            'WHERE consumer_pk = :id)';
        $query = oci_parse($this->db, $sql);
        oci_bind_by_name($query, 'id', $id);
        oci_execute($query);

// Delete any outstanding share keys for resource links for contexts in this consumer
        $sql = "DELETE FROM {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . ' ' .
            "WHERE resource_link_pk IN (SELECT resource_link_pk FROM {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_TABLE_NAME . ' rl ' .
            "INNER JOIN {$this->dbTableNamePrefix}" . static::CONTEXT_TABLE_NAME . ' c ON rl.context_pk = c.context_pk WHERE c.consumer_pk = :id)';
        $query = oci_parse($this->db, $sql);
        oci_bind_by_name($query, 'id', $id);
        oci_execute($query);

// Delete any users in resource links for this consumer
        $sql = "DELETE FROM {$this->dbTableNamePrefix}" . static::USER_RESULT_TABLE_NAME . ' ' .
            "WHERE resource_link_pk IN (SELECT resource_link_pk FROM {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_TABLE_NAME . ' ' .
            'WHERE consumer_pk = :id)';
        $query = oci_parse($this->db, $sql);
        oci_bind_by_name($query, 'id', $id);
        oci_execute($query);

// Delete any users in resource links for contexts in this consumer
        $sql = "DELETE FROM {$this->dbTableNamePrefix}" . static::USER_RESULT_TABLE_NAME . ' ' .
            "WHERE resource_link_pk IN (SELECT resource_link_pk FROM {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_TABLE_NAME . ' rl ' .
            "INNER JOIN {$this->dbTableNamePrefix}" . static::CONTEXT_TABLE_NAME . ' c ON rl.context_pk = c.context_pk WHERE c.consumer_pk = :id)';
        $query = oci_parse($this->db, $sql);
        oci_bind_by_name($query, 'id', $id);
        oci_execute($query);

// Update any resource links for which this consumer is acting as a primary resource link
        $sql = "UPDATE {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_TABLE_NAME . ' ' .
            'SET primary_resource_link_pk = NULL, share_approved = NULL ' .
            'WHERE primary_resource_link_pk IN ' .
            "(SELECT resource_link_pk FROM {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_TABLE_NAME . ' ' .
            'WHERE consumer_pk = :id)';
        $query = oci_parse($this->db, $sql);
        oci_bind_by_name($query, 'id', $id);
        oci_execute($query);

// Update any resource links for contexts in which this consumer is acting as a primary resource link
        $sql = "UPDATE {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_TABLE_NAME . ' ' .
            'SET primary_resource_link_pk = NULL, share_approved = NULL ' .
            'WHERE primary_resource_link_pk IN ' .
            "(SELECT rl.resource_link_pk FROM {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_TABLE_NAME . ' rl ' .
            "INNER JOIN {$this->dbTableNamePrefix}" . static::CONTEXT_TABLE_NAME . ' c ON rl.context_pk = c.context_pk ' .
            'WHERE c.consumer_pk = :id)';
        $query = oci_parse($this->db, $sql);
        oci_bind_by_name($query, 'id', $id);
        oci_execute($query);

// Delete any resource links for this consumer
        $sql = "DELETE FROM {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_TABLE_NAME . ' ' .
            'WHERE consumer_pk = :id';
        $query = oci_parse($this->db, $sql);
        oci_bind_by_name($query, 'id', $id);
        oci_execute($query);

// Delete any resource links for contexts in this consumer
        $sql = "DELETE FROM {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_TABLE_NAME . ' ' .
            'WHERE context_pk IN (' .
            "SELECT context_pk FROM {$this->dbTableNamePrefix}" . static::CONTEXT_TABLE_NAME . ' ' . 'WHERE consumer_pk = :id)';
        $query = oci_parse($this->db, $sql);
        oci_bind_by_name($query, 'id', $id);
        oci_execute($query);

// Delete any contexts for this consumer
        $sql = "DELETE FROM {$this->dbTableNamePrefix}" . static::CONTEXT_TABLE_NAME . ' ' .
            'WHERE consumer_pk = :id';
        $query = oci_parse($this->db, $sql);
        oci_bind_by_name($query, 'id', $id);
        oci_execute($query);

// Delete consumer
        $sql = "DELETE FROM {$this->dbTableNamePrefix}" . static::CONSUMER_TABLE_NAME . ' ' .
            'WHERE consumer_pk = :id';
        $query = oci_parse($this->db, $sql);
        oci_bind_by_name($query, 'id', $id);
        $ok = oci_execute($query);

        if ($ok) {
            $consumer->initialize();
        }

        return $ok;
    }

    /**
     * Load tool consumer objects.
     *
     * @return ToolConsumer[] Array of all defined ToolConsumer objects
     */
    public function getToolConsumers()
    {
        $consumers = array();

        $sql = 'SELECT consumer_pk, name, consumer_key256, consumer_key, secret, lti_version, ' .
            'signature_method, consumer_name, consumer_version, consumer_guid, ' .
            'profile, tool_proxy, settings, protected, enabled, ' .
            'enable_from, enable_until, last_access, created, updated ' .
            "FROM {$this->dbTableNamePrefix}" . static::CONSUMER_TABLE_NAME . ' ' .
            'ORDER BY name';
        $query = oci_parse($this->db, $sql);
        $ok = ($query !== FALSE);

        if ($ok) {
            $ok = oci_execute($query);
        }

        if ($ok) {
            while ($row = oci_fetch_assoc($query)) {
                $row = array_change_key_case($row);
                $key = empty($row['consumer_key']) ? $row['consumer_key256'] : $row['consumer_key'];
                $consumer = new LTI\ToolConsumer($key, $this);
                $consumer->setRecordId(intval($row['consumer_pk']));
                $consumer->name = $row['name'];
                $consumer->secret = $row['secret'];
                $consumer->ltiVersion = $row['lti_version'];
                $consumer->signatureMethod = $row['signature_method'];
                $consumer->consumerName = $row['consumer_name'];
                $consumer->consumerVersion = $row['consumer_version'];
                $consumer->consumerGuid = $row['consumer_guid'];
                $consumer->profile = json_decode($row['profile']);
                $consumer->toolProxy = $row['tool_proxy'];
                $settingsValue = $row['settings']->load();
                if (is_string($settingsValue)) {
                    $settings = json_decode($settingsValue, TRUE);
                    if (!is_array($settings)) {
                        $settings = @unserialize($settingsValue);  // check for old serialized setting
                    }
                    if (!is_array($settings)) {
                        $settings = array();
                    }
                } else {
                    $settings = array();
                }
                $consumer->setSettings($settings);
                $consumer->protected = (intval($row['protected']) === 1);
                $consumer->enabled = (intval($row['enabled']) === 1);
                $consumer->enableFrom = null;
                if (!is_null($row['enable_from'])) {
                    $consumer->enableFrom = strtotime($row['enable_from']);
                }
                $consumer->enableUntil = null;
                if (!is_null($row['enable_until'])) {
                    $consumer->enableUntil = strtotime($row['enable_until']);
                }
                $consumer->lastAccess = null;
                if (!is_null($row['last_access'])) {
                    $consumer->lastAccess = strtotime($row['last_access']);
                }
                $consumer->created = strtotime($row['created']);
                $consumer->updated = strtotime($row['updated']);
                $consumers[] = $consumer;
            }
        }

        return $consumers;
    }

###
###  Context methods
###

    /**
     * Load context object.
     *
     * @param Context $context Context object
     *
     * @return bool    True if the context object was successfully loaded
     */
    public function loadContext($context)
    {
        $ok = false;
        if (!is_null($context->getRecordId())) {
            $sql = 'SELECT context_pk, consumer_pk, title, lti_context_id, type, settings, created, updated ' .
                "FROM {$this->dbTableNamePrefix}" . static::CONTEXT_TABLE_NAME . ' ' .
                'WHERE (context_pk = :id)';
            $query = oci_parse($this->db, $sql);
            $id = $context->getRecordId();
            oci_bind_by_name($query, 'id', $id);
        } else {
            $sql = 'SELECT context_pk, consumer_pk, title, lti_context_id, type, settings, created, updated ' .
                "FROM {$this->dbTableNamePrefix}" . static::CONTEXT_TABLE_NAME . ' ' .
                'WHERE (consumer_pk = :cid) AND (lti_context_id = :ctx)';
            $query = oci_parse($this->db, $sql);
            $id = $context->getConsumer()->getRecordId();
            oci_bind_by_name($query, 'cid', $id);
            oci_bind_by_name($query, 'ctx', $context->ltiContextId);
        }
        $ok = oci_execute($query);
        if ($ok) {
            $row = oci_fetch_assoc($query);
            $ok = ($row !== FALSE);
        }
        if ($ok) {
            $row = array_change_key_case($row);
            $context->setRecordId(intval($row['context_pk']));
            $context->setConsumerId(intval($row['consumer_pk']));
            $context->ltiContextId = $row['title'];
            $context->ltiContextId = $row['lti_context_id'];
            $context->type = $row['type'];
            $settingsValue = $row['settings']->load();
            if (is_string($settingsValue)) {
                $settings = json_decode($settingsValue, TRUE);
                if (!is_array($settings)) {
                    $settings = @unserialize($settingsValue);  // check for old serialized setting
                }
                if (!is_array($settings)) {
                    $settings = array();
                }
            } else {
                $settings = array();
            }
            $context->setSettings($settings);
            $context->created = strtotime($row['created']);
            $context->updated = strtotime($row['updated']);
        }

        return $ok;
    }

    /**
     * Save context object.
     *
     * @param Context $context Context object
     *
     * @return bool    True if the context object was successfully saved
     */
    public function saveContext($context)
    {
        $time = time();
        $now = date("{$this->dateFormat} {$this->timeFormat}", $time);
        $settingsValue = json_encode($context->getSettings());
        $id = $context->getRecordId();
        $consumer_pk = $context->getConsumer()->getRecordId();
        if (empty($id)) {
            $sql = "INSERT INTO {$this->dbTableNamePrefix}" . static::CONTEXT_TABLE_NAME . ' (consumer_pk, title, ' .
                'lti_context_id, type, settings, created, updated) ' .
                'VALUES (:cid, :title, :ctx, :type, :settings, :created, :updated) returning context_pk into :pk';
            $query = oci_parse($this->db, $sql);
            oci_bind_by_name($query, 'cid', $consumer_pk);
            oci_bind_by_name($query, 'title', $context->title);
            oci_bind_by_name($query, 'ctx', $context->ltiContextId);
            oci_bind_by_name($query, 'type', $context->type);
            oci_bind_by_name($query, 'settings', $settingsValue);
            oci_bind_by_name($query, 'created', $now);
            oci_bind_by_name($query, 'updated', $now);
            oci_bind_by_name($query, 'pk', $pk);
        } else {
            $sql = "UPDATE {$this->dbTableNamePrefix}" . static::CONTEXT_TABLE_NAME . ' SET ' .
                'title = :title, lti_context_id = :ctx, type = :type, settings = :settings, ' .
                'updated = :updated ' .
                'WHERE (consumer_pk = :cid) AND (context_pk = :ctxid)';
            $query = oci_parse($this->db, $sql);
            oci_bind_by_name($query, 'title', $context->title);
            oci_bind_by_name($query, 'ctx', $context->ltiContextId);
            oci_bind_by_name($query, 'type', $context->type);
            oci_bind_by_name($query, 'settings', $settingsValue);
            oci_bind_by_name($query, 'updated', $now);
            oci_bind_by_name($query, 'cid', $consumer_pk);
            oci_bind_by_name($query, 'ctxid', $id);
        }
        $ok = oci_execute($query);
        if ($ok) {
            if (empty($id)) {
                $context->setRecordId(intval($pk));
                $context->created = $time;
            }
            $context->updated = $time;
        }

        return $ok;
    }

    /**
     * Delete context object.
     *
     * @param Context $context Context object
     *
     * @return bool    True if the Context object was successfully deleted
     */
    public function deleteContext($context)
    {
        $id = $context->getRecordId();

// Delete any outstanding share keys for resource links for this context
        $sql = "DELETE FROM {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . ' ' .
            "WHERE resource_link_pk IN (SELECT resource_link_pk FROM {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_TABLE_NAME . ' ' .
            'WHERE context_pk = :id)';
        $query = oci_parse($this->db, $sql);
        oci_bind_by_name($query, 'id', $id);
        oci_execute($query);

// Delete any users in resource links for this context
        $sql = "DELETE FROM {$this->dbTableNamePrefix}" . static::USER_RESULT_TABLE_NAME . ' ' .
            "WHERE resource_link_pk IN (SELECT resource_link_pk FROM {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_TABLE_NAME . ' ' .
            'WHERE context_pk = :id)';
        $query = oci_parse($this->db, $sql);
        oci_bind_by_name($query, 'id', $id);
        oci_execute($query);

// Update any resource links for which this consumer is acting as a primary resource link
        $sql = "UPDATE {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_TABLE_NAME . ' ' .
            'SET primary_resource_link_pk = null, share_approved = null ' .
            'WHERE primary_resource_link_pk IN ' .
            "(SELECT resource_link_pk FROM {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_TABLE_NAME . ' WHERE context_pk = :id)';
        $query = oci_parse($this->db, $sql);
        oci_bind_by_name($query, 'id', $id);
        oci_execute($query);

// Delete any resource links for this consumer
        $sql = "DELETE FROM {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_TABLE_NAME . ' ' .
            'WHERE context_pk = :id';
        $query = oci_parse($this->db, $sql);
        oci_bind_by_name($query, 'id', $id);
        oci_execute($query);

// Delete context
        $sql = "DELETE FROM {$this->dbTableNamePrefix}" . static::CONTEXT_TABLE_NAME . ' ' .
            'WHERE context_pk = :id';
        $query = oci_parse($this->db, $sql);
        oci_bind_by_name($query, 'id', $id);
        $ok = oci_execute($query);

        if ($ok) {
            $context->initialize();
        }

        return $ok;
    }

###
###  ResourceLink methods
###

    /**
     * Load resource link object.
     *
     * @param ResourceLink $resourceLink ResourceLink object
     *
     * @return bool    True if the resource link object was successfully loaded
     */
    public function loadResourceLink($resourceLink)
    {
        if (!is_null($resourceLink->getRecordId())) {
            $sql = 'SELECT resource_link_pk, context_pk, consumer_pk, lti_resource_link_id, settings, primary_resource_link_pk, share_approved, created, updated ' .
                "FROM {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_TABLE_NAME . ' ' .
                'WHERE (resource_link_pk = :id)';
            $query = oci_parse($this->db, $sql);
            $id = $resourceLink->getRecordId();
            oci_bind_by_name($query, 'id', $id);
        } elseif (!is_null($resourceLink->getContext())) {
            $sql = 'SELECT resource_link_pk, context_pk, consumer_pk, lti_resource_link_id, settings, primary_resource_link_pk, share_approved, created, updated ' .
                "FROM {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_TABLE_NAME . ' ' .
                'WHERE (context_pk = :id) AND (lti_resource_link_id = :rlid)';
            $query = oci_parse($this->db, $sql);
            $id = $resourceLink->getContext()->getRecordId();
            oci_bind_by_name($query, 'id', $id);
            $rlid = $resourceLink->getId();
            oci_bind_by_name($query, 'rlid', $rlid);
        } else {
            $sql = 'SELECT r.resource_link_pk, r.context_pk, r.consumer_pk, r.lti_resource_link_id, r.settings, r.primary_resource_link_pk, r.share_approved, r.created, r.updated ' .
                "FROM {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_TABLE_NAME . ' r LEFT OUTER JOIN ' .
                $this->dbTableNamePrefix . static::CONTEXT_TABLE_NAME . ' c ON r.context_pk = c.context_pk ' .
                ' WHERE ((r.consumer_pk = :id1) OR (c.consumer_pk = :id2)) AND (lti_resource_link_id = :rlid)';
            $query = oci_parse($this->db, $sql);
            $id1 = $resourceLink->getConsumer()->getRecordId();
            oci_bind_by_name($query, 'id1', $id1);
            $id2 = $resourceLink->getConsumer()->getRecordId();
            oci_bind_by_name($query, 'id2', $id2);
            $id = $resourceLink->getId();
            oci_bind_by_name($query, 'rlid', $id);
        }
        $ok = oci_execute($query);
        if ($ok) {
            $row = oci_fetch_assoc($query);
            $ok = ($row !== FALSE);
        }

        if ($ok) {
            $row = array_change_key_case($row);
            $resourceLink->setRecordId(intval($row['resource_link_pk']));
            if (!is_null($row['context_pk'])) {
                $resourceLink->setContextId(intval($row['context_pk']));
            } else {
                $resourceLink->setContextId(null);
            }
            if (!is_null($row['consumer_pk'])) {
                $resourceLink->setConsumerId(intval($row['consumer_pk']));
            } else {
                $resourceLink->setConsumerId(null);
            }
            $resourceLink->ltiResourceLinkId = $row['lti_resource_link_id'];
            $settings = $row['settings']->load();
            $settingsValue = $row['settings']->load();
            if (is_string($settingsValue)) {
                $settings = json_decode($settingsValue, TRUE);
                if (!is_array($settings)) {
                    $settings = @unserialize($settingsValue);  // check for old serialized setting
                }
                if (!is_array($settings)) {
                    $settings = array();
                }
            } else {
                $settings = array();
            }
            $resourceLink->setSettings($settings);
            if (!is_null($row['primary_resource_link_pk'])) {
                $resourceLink->primaryResourceLinkId = intval($row['primary_resource_link_pk']);
            } else {
                $resourceLink->primaryResourceLinkId = null;
            }
            $resourceLink->shareApproved = (is_null($row['share_approved'])) ? null : (intval($row['share_approved']) === 1);
            $resourceLink->created = strtotime($row['created']);
            $resourceLink->updated = strtotime($row['updated']);
        }

        return $ok;
    }

    /**
     * Save resource link object.
     *
     * @param ResourceLink $resourceLink ResourceLink object
     *
     * @return bool    True if the resource link object was successfully saved
     */
    public function saveResourceLink($resourceLink)
    {
        $time = time();
        $now = date("{$this->dateFormat} {$this->timeFormat}", $time);
        $settingsValue = json_encode($resourceLink->getSettings());
        if (!is_null($resourceLink->getContext())) {
            $consumerId = null;
            $contextId = strval($resourceLink->getContext()->getRecordId());
        } elseif (!is_null($resourceLink->getContextId())) {
            $consumerId = null;
            $contextId = strval($resourceLink->getContextId());
        } else {
            $consumerId = strval($resourceLink->getConsumer()->getRecordId());
            $contextId = null;
        }
        if (empty($resourceLink->primaryResourceLinkId)) {
            $primaryResourceLinkId = null;
        } else {
            $primaryResourceLinkId = $resourceLink->primaryResourceLinkId;
        }
        $id = $resourceLink->getRecordId();
        if (empty($id)) {
            $sql = "INSERT INTO {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_TABLE_NAME . ' (consumer_pk, context_pk, ' .
                'lti_resource_link_id, settings, primary_resource_link_pk, share_approved, created, updated) ' .
                'VALUES (:cid, :ctx, :rlid, :settings, :prlid, :share_approved, :created, :updated) returning resource_link_pk into :pk';
            $query = oci_parse($this->db, $sql);
            oci_bind_by_name($query, 'cid', $consumerId);
            oci_bind_by_name($query, 'ctx', $contextId);
            $rlid = $resourceLink->getId();
            oci_bind_by_name($query, 'rlid', $rlid);
            oci_bind_by_name($query, 'settings', $settingsValue);
            oci_bind_by_name($query, 'prlid', $primaryResourceLinkId);
            oci_bind_by_name($query, 'share_approved', $resourceLink->shareApproved);
            oci_bind_by_name($query, 'created', $now);
            oci_bind_by_name($query, 'updated', $now);
            oci_bind_by_name($query, 'pk', $pk);
        } elseif (!is_null($contextId)) {
            $sql = "UPDATE {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_TABLE_NAME . ' SET ' .
                'consumer_pk = NULL, context_pk = :ctx, lti_resource_link_id = :rlid, settings = :settings, ' .
                'primary_resource_link_pk = :prlid, share_approved = :share_approved, updated = :updated ' .
                'WHERE (resource_link_pk = :id)';
            $query = oci_parse($this->db, $sql);
            oci_bind_by_name($query, 'ctx', $contextId);
            $rlid = $resourceLink->getId();
            oci_bind_by_name($query, 'rlid', $rlid);
            oci_bind_by_name($query, 'settings', $settingsValue);
            oci_bind_by_name($query, 'prlid', $primaryResourceLinkId);
            oci_bind_by_name($query, 'share_approved', $resourceLink->shareApproved);
            oci_bind_by_name($query, 'updated', $now);
            oci_bind_by_name($query, 'id', $id);
        } else {
            $sql = "UPDATE {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_TABLE_NAME . ' SET ' .
                'context_pk = :ctx, lti_resource_link_id = :rlid, settings = :settings, ' .
                'primary_resource_link_pk = :prlid, share_approved = :share_approved, updated = :updated ' .
                'WHERE (consumer_pk = :cid) AND (resource_link_pk = :id)';
            $query = oci_parse($this->db, $sql);
            oci_bind_by_name($query, 'ctx', $contextId);
            $rlid = $resourceLink->getId();
            oci_bind_by_name($query, 'rlid', $rlid);
            oci_bind_by_name($query, 'settings', $settingsValue);
            oci_bind_by_name($query, 'prlid', $primaryResourceLinkId);
            oci_bind_by_name($query, 'share_approved', $resourceLink->shareApproved);
            oci_bind_by_name($query, 'updated', $now);
            oci_bind_by_name($query, 'cid', $consumerId);
            oci_bind_by_name($query, 'id', $id);
        }
        $ok = oci_execute($query);
        if ($ok) {
            if (empty($id)) {
                $resourceLink->setRecordId(intval($pk));
                $resourceLink->created = $time;
            }
            $resourceLink->updated = $time;
        }

        return $ok;
    }

    /**
     * Delete resource link object.
     *
     * @param ResourceLink $resourceLink ResourceLink object
     *
     * @return bool    True if the resource link object was successfully deleted
     */
    public function deleteResourceLink($resourceLink)
    {
        $id = $resourceLink->getRecordId();

// Delete any outstanding share keys for resource links for this consumer
        $sql = "DELETE FROM {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . ' ' .
            'WHERE (resource_link_pk = :id)';
        $query = oci_parse($this->db, $sql);
        oci_bind_by_name($query, 'id', $id);
        $ok = oci_execute($query);

// Delete users
        if ($ok) {
            $sql = "DELETE FROM {$this->dbTableNamePrefix}" . static::USER_RESULT_TABLE_NAME . ' ' .
                'WHERE (resource_link_pk = :id)';
            $query = oci_parse($this->db, $sql);
            oci_bind_by_name($query, 'id', $id);
            $ok = oci_execute($query);
        }

// Update any resource links for which this is the primary resource link
        if ($ok) {
            $sql = "UPDATE {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_TABLE_NAME . ' ' .
                'SET primary_resource_link_pk = NULL ' .
                'WHERE (primary_resource_link_pk = :id)';
            $query = oci_parse($this->db, $sql);
            oci_bind_by_name($query, 'id', $id);
            $ok = oci_execute($query);
        }

// Delete resource link
        if ($ok) {
            $sql = "DELETE FROM {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_TABLE_NAME . ' ' .
                'WHERE (resource_link_pk = :id)';
            $query = oci_parse($this->db, $sql);
            oci_bind_by_name($query, 'id', $id);
            $ok = oci_execute($query);
        }

        if ($ok) {
            $resourceLink->initialize();
        }

        return $ok;
    }

    /**
     * Get array of user objects.
     *
     * Obtain an array of UserResult objects for users with a result sourcedId.  The array may include users from other
     * resource links which are sharing this resource link.  It may also be optionally indexed by the user ID of a specified scope.
     *
     * @param ResourceLink $resourceLink      Resource link object
     * @param bool        $localOnly True if only users within the resource link are to be returned (excluding users sharing this resource link)
     * @param int         $idScope     Scope value to use for user IDs
     *
     * @return UserResult[] Array of UserResult objects
     */
    public function getUserResultSourcedIDsResourceLink($resourceLink, $localOnly, $idScope)
    {
        $id = $resourceLink->getRecordId();
        $userResults = array();

        if ($localOnly) {
            $sql = 'SELECT u.user_result_pk, u.lti_result_sourcedid, u.lti_user_id, u.created, u.updated ' .
                "FROM {$this->dbTableNamePrefix}" . static::USER_RESULT_TABLE_NAME . ' u ' .
                "INNER JOIN {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_TABLE_NAME . ' rl ' .
                'ON u.resource_link_pk = rl.resource_link_pk ' .
                'WHERE (rl.resource_link_pk = :id) AND (rl.primary_resource_link_pk IS NULL)';
            $query = oci_parse($this->db, $sql);
            oci_bind_by_name($query, 'id', $id);
        } else {
            $sql = 'SELECT u.user_result_pk, u.lti_result_sourcedid, u.lti_user_id, u.created, u.updated ' .
                "FROM {$this->dbTableNamePrefix}" . static::USER_RESULT_TABLE_NAME . ' u ' .
                "INNER JOIN {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_TABLE_NAME . ' rl ' .
                'ON u.resource_link_pk = rl.resource_link_pk ' .
                'WHERE ((rl.resource_link_pk = :id) AND (rl.primary_resource_link_pk IS NULL)) OR ' .
                '((rl.primary_resource_link_pk = :pid) AND (share_approved = 1))';
            $query = oci_parse($this->db, $sql);
            oci_bind_by_name($query, 'id', $id);
            oci_bind_by_name($query, 'pid', $id);
        }
        if (oci_execute($query)) {
            while ($row = oci_fetch_assoc($query)) {
                $row = array_change_key_case($row);
                $userresult = LTI\UserResult::fromRecordId($row['user_result_pk'], $resourceLink->getDataConnector());
                $userresult->setRecordId(intval($row['user_result_pk']));
                $userresult->ltiResultSourcedId = $row['lti_result_sourcedid'];
                $userresult->created = strtotime($row['created']);
                $userresult->updated = strtotime($row['updated']);
                if (is_null($idScope)) {
                    $userResults[] = $userresult;
                } else {
                    $userResults[$userresult->getId($idScope)] = $userresult;
                }
            }
        }

        return $userResults;
    }

    /**
     * Get array of shares defined for this resource link.
     *
     * @param ResourceLink $resourceLink ResourceLink object
     *
     * @return ResourceLinkShare[] Array of ResourceLinkShare objects
     */
    public function getSharesResourceLink($resourceLink)
    {
        $id = $resourceLink->getRecordId();

        $shares = array();

        $sql = 'SELECT c.consumer_name, r.resource_link_pk, r.title, r.share_approved ' .
            "FROM {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_TABLE_NAME . ' r ' .
            "INNER JOIN {$this->dbTableNamePrefix}" . static::CONSUMER_TABLE_NAME . ' c ON r.consumer_pk = c.consumer_pk ' .
            'WHERE (r.primary_resource_link_pk = :id1) ' .
            'UNION ' .
            'SELECT c2.consumer_name, r2.resource_link_pk, r2.title, r2.share_approved ' .
            "FROM {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_TABLE_NAME . ' r2 ' .
            "INNER JOIN {$this->dbTableNamePrefix}" . static::CONTEXT_TABLE_NAME . ' x ON r2.context_pk = x.context_pk ' .
            "INNER JOIN {$this->dbTableNamePrefix}" . static::CONSUMER_TABLE_NAME . ' c2 ON x.consumer_pk = c2.consumer_pk ' .
            'WHERE (r2.primary_resource_link_pk = :id2) ' .
            'ORDER BY consumer_name, title';
        $query = oci_parse($this->db, $sql);
        oci_bind_by_name($query, 'id1', $id);
        oci_bind_by_name($query, 'id2', $id);
        if (oci_execute($query)) {
            while ($row = oci_fetch_assoc($query)) {
                $row = array_change_key_case($row);
                $share = new LTI\ResourceLinkShare();
                $share->resourceLinkId = intval($row['resource_link_pk']);
                $share->approved = (intval($row['share_approved']) === 1);
                $shares[] = $share;
            }
        }

        return $shares;
    }

###
###  ConsumerNonce methods
###

    /**
     * Load nonce object.
     *
     * @param ConsumerNonce $nonce Nonce object
     *
     * @return bool    True if the nonce object was successfully loaded
     */
    public function loadConsumerNonce($nonce)
    {
// Delete any expired nonce values
        $now = date("{$this->dateFormat} {$this->timeFormat}", time());
        $sql = "DELETE FROM {$this->dbTableNamePrefix}" . static::NONCE_TABLE_NAME . ' WHERE expires <= :now';
        $query = oci_parse($this->db, $sql);
        oci_bind_by_name($query, 'now', $now);
        oci_execute($query);

// Load the nonce
        $id = $nonce->getConsumer()->getRecordId();
        $value = $nonce->getValue();
        $sql = "SELECT value T FROM {$this->dbTableNamePrefix}" . static::NONCE_TABLE_NAME . ' WHERE (consumer_pk = :id) AND (value = :value)';
        $query = oci_parse($this->db, $sql);
        oci_bind_by_name($query, 'id', $id);
        oci_bind_by_name($query, 'value', $value);
        $ok = oci_execute($query);
        if ($ok) {
            $row = oci_fetch_assoc($query);
            if ($row === false) {
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * Save nonce object.
     *
     * @param ConsumerNonce $nonce Nonce object
     *
     * @return bool    True if the nonce object was successfully saved
     */
    public function saveConsumerNonce($nonce)
    {
        $id = $nonce->getConsumer()->getRecordId();
        $value = $nonce->getValue();
        $expires = date("{$this->dateFormat} {$this->timeFormat}", $nonce->expires);
        $sql = "INSERT INTO {$this->dbTableNamePrefix}" . static::NONCE_TABLE_NAME . ' (consumer_pk, value, expires) VALUES (:id, :value, :expires)';
        $query = oci_parse($this->db, $sql);
        oci_bind_by_name($query, 'id', $id);
        oci_bind_by_name($query, 'value', $value);
        oci_bind_by_name($query, 'expires', $expires);
        $ok = oci_execute($query);

        return $ok;
    }

###
###  ResourceLinkShareKey methods
###

    /**
     * Load resource link share key object.
     *
     * @param ResourceLinkShareKey $shareKey ResourceLink share key object
     *
     * @return bool    True if the resource link share key object was successfully loaded
     */
    public function loadResourceLinkShareKey($shareKey)
    {
        $ok = false;

// Clear expired share keys
        $now = date("{$this->dateFormat} {$this->timeFormat}", time());
        $sql = "DELETE FROM {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . ' WHERE expires <= :now';
        $query = oci_parse($this->db, $sql);
        oci_bind_by_name($query, 'now', $now);
        oci_execute($query);

// Load share key
        $id = $shareKey->getId();
        $sql = 'SELECT resource_link_pk, auto_approve, expires ' .
            "FROM {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . ' ' .
            'WHERE share_key_id = :id';
        $query = oci_parse($this->db, $sql);
        oci_bind_by_name($query, 'id', $id);
        if (oci_execute($query)) {
            $row = oci_fetch_assoc($query);
            if ($row !== FALSE) {
                $row = array_change_key_case($row);
                if (intval($row['resource_link_pk']) === $shareKey->resourceLinkId) {
                    $shareKey->autoApprove = ($row['auto_approve'] === 1);
                    $shareKey->expires = strtotime($row['expires']);
                    $ok = true;
                }
            }
        }

        return $ok;
    }

    /**
     * Save resource link share key object.
     *
     * @param ResourceLinkShareKey $shareKey Resource link share key object
     *
     * @return bool    True if the resource link share key object was successfully saved
     */
    public function saveResourceLinkShareKey($shareKey)
    {
        if ($shareKey->autoApprove) {
            $approve = 1;
        } else {
            $approve = 0;
        }
        $id = $shareKey->getId();
        $expires = date("{$this->dateFormat} {$this->timeFormat}", $shareKey->expires);
        $sql = "INSERT INTO {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . ' ' .
            '(share_key_id, resource_link_pk, auto_approve, expires) ' .
            'VALUES (:id, :prlid, :approve, :expires)';
        $query = oci_parse($this->db, $sql);
        oci_bind_by_name($query, 'id', $id);
        oci_bind_by_name($query, 'prlid', $shareKey->resourceLinkId);
        oci_bind_by_name($query, 'approve', $approve);
        oci_bind_by_name($query, 'expires', $expires);
        $ok = oci_execute($query);

        return $ok;
    }

    /**
     * Delete resource link share key object.
     *
     * @param ResourceLinkShareKey $shareKey Resource link share key object
     *
     * @return bool    True if the resource link share key object was successfully deleted
     */
    public function deleteResourceLinkShareKey($shareKey)
    {
        $id = $shareKey->getId();
        $sql = "DELETE FROM {$this->dbTableNamePrefix}" . static::RESOURCE_LINK_SHARE_KEY_TABLE_NAME . ' WHERE share_key_id = :id';
        $query = oci_parse($this->db, $sql);
        oci_bind_by_name($query, 'id', $id);
        $ok = oci_execute($query);

        if ($ok) {
            $shareKey->initialize();
        }

        return $ok;
    }

###
###  UserResult Result methods
###

    /**
     * Load user object.
     *
     * @param UserResult $userresult UserResult object
     *
     * @return bool    True if the user object was successfully loaded
     */
    public function loadUserResult($userresult)
    {
        $ok = false;
        if (!is_null($userresult->getRecordId())) {
            $id = $userresult->getRecordId();
            $sql = 'SELECT user_result_pk, resource_link_pk, lti_user_id, lti_result_sourcedid, created, updated ' .
                "FROM {$this->dbTableNamePrefix}" . static::USER_RESULT_TABLE_NAME . ' ' .
                'WHERE (user_result_pk = :id)';
            $query = oci_parse($this->db, $sql);
            oci_bind_by_name($query, 'id', $id);
        } else {
            $id = $userresult->getResourceLink()->getRecordId();
            $uid = $userresult->getId(LTI\ToolProvider::ID_SCOPE_ID_ONLY);
            $sql = 'SELECT user_result_pk, resource_link_pk, lti_user_id, lti_result_sourcedid, created, updated ' .
                "FROM {$this->dbTableNamePrefix}" . static::USER_RESULT_TABLE_NAME . ' ' .
                'WHERE (resource_link_pk = :id) AND (lti_user_id = :u_id)';
            $query = oci_parse($this->db, $sql);
            oci_bind_by_name($query, 'id', $id);
            oci_bind_by_name($query, 'u_id', $uid);
        }
        if (oci_execute($query)) {
            $row = oci_fetch_assoc($query);
            if ($row !== false) {
                $row = array_change_key_case($row);
                $userresult->setRecordId(intval($row['user_result_pk']));
                $userresult->setResourceLinkId(intval($row['resource_link_pk']));
                $userresult->ltiUserId = $row['lti_user_id'];
                $userresult->ltiResultSourcedId = $row['lti_result_sourcedid'];
                $userresult->created = strtotime($row['created']);
                $userresult->updated = strtotime($row['updated']);
                $ok = true;
            }
        }

        return $ok;
    }

    /**
     * Save user object.
     *
     * @param UserResult $userresult UserResult object
     *
     * @return bool    True if the user object was successfully saved
     */
    public function saveUserResult($userresult)
    {
        $time = time();
        $now = date("{$this->dateFormat} {$this->timeFormat}", $time);
        if (is_null($userresult->created)) {
            $sql = "INSERT INTO {$this->dbTableNamePrefix}" . static::USER_RESULT_TABLE_NAME . ' (resource_link_pk, ' .
                'lti_user_id, lti_result_sourcedid, created, updated) ' .
                'VALUES (:rlid, :u_id, :sourcedid, :created, :updated) returning user_result_pk into :pk';
            $query = oci_parse($this->db, $sql);
            $rlid = $userresult->getResourceLink()->getRecordId();
            oci_bind_by_name($query, 'rlid', $rlid);
            $uid = $userresult->getId(LTI\ToolProvider::ID_SCOPE_ID_ONLY);
            oci_bind_by_name($query, 'u_id', $uid);
            $sourcedid = $userresult->ltiResultSourcedId;
            oci_bind_by_name($query, 'sourcedid', $sourcedid);
            oci_bind_by_name($query, 'created', $now);
            oci_bind_by_name($query, 'updated', $now);
            oci_bind_by_name($query, 'pk', $pk);
        } else {
            $sql = "UPDATE {$this->dbTableNamePrefix}" . static::USER_RESULT_TABLE_NAME . ' ' .
                'SET lti_result_sourcedid = :sourcedid, updated = :updated ' .
                'WHERE (user_result_pk = :id)';
            $query = oci_parse($this->db, $sql);
            $sourcedid = $userresult->ltiResultSourcedId;
            oci_bind_by_name($query, 'sourcedid', $sourcedid);
            oci_bind_by_name($query, 'updated', $now);
            $id = $userresult->getRecordId();
            oci_bind_by_name($query, 'id', $id);
        }
        $ok = oci_execute($query);
        if ($ok) {
            if (is_null($userresult->created)) {
                $userresult->setRecordId(intval($pk));
                $userresult->created = $time;
            }
            $userresult->updated = $time;
        }

        return $ok;
    }

    /**
     * Delete user object.
     *
     * @param UserResult $userresult UserResult object
     *
     * @return bool    True if the user object was successfully deleted
     */
    public function deleteUserResult($userresult)
    {
        $sql = "DELETE FROM {$this->dbTableNamePrefix}" . static::USER_RESULT_TABLE_NAME . ' ' .
            'WHERE (user_result_pk = :id)';
        $query = oci_parse($this->db, $sql);
        $id = $userresult->getRecordId();
        oci_bind_by_name($query, 'id', $id);
        $ok = oci_execute($query);

        if ($ok) {
            $userresult->initialize();
        }

        return $ok;
    }

}
