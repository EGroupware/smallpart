<?php

namespace ceLTIc\LTI\ApiHook;

/**
 * Trait to handle API hook registrations
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @copyright  SPV Software Products
 * @license  http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3
 */
trait ApiHook
{

    /**
     * User Id hook name.
     */
    public static $USER_ID_HOOK = "UserId";

    /**
     * Context Id hook name.
     */
    public static $CONTEXT_ID_HOOK = "ContextId";

    /**
     * Memberships service hook name.
     */
    public static $MEMBERSHIPS_SERVICE_HOOK = "Memberships";

    /**
     * Outcomes service hook name.
     */
    public static $OUTCOMES_SERVICE_HOOK = "Outcomes";

    /**
     * Tool Settings service hook name.
     */
    public static $TOOL_SETTINGS_SERVICE_HOOK = "ToolSettings";

    /**
     * API hook class names.
     */
    private static $API_HOOKS = array();

    /**
     * Register the availability of an API hook.
     *
     * @param string $hookName  Name of hook
     * @param string $familyCode  Family code for current platform
     * @param string $className  Name of implementing class
     */
    public static function registerApiHook($hookName, $familyCode, $className)
    {
        $objectClass = get_class();
        self::$API_HOOKS["{$objectClass}-{$hookName}-{$familyCode}"] = $className;
    }

    /**
     * Get the class name for an API hook.
     *
     * @param string $hookName  Name of hook
     * @param string $familyCode  Family code for current platform
     */
    private static function getApiHook($hookName, $familyCode)
    {
        $class = self::class;
        return self::$API_HOOKS["{$class}-{$hookName}-{$familyCode}"];
    }

    /**
     * Check if an API hook is available.
     *
     * @param string $hookName    Name of hook
     * @param string $familyCode  Family code for current platform
     *
     * @return bool    True if an API hook is available
     */
    private static function hasApiHook($hookName, $familyCode)
    {
        $class = self::class;
        return isset(self::$API_HOOKS["{$class}-{$hookName}-{$familyCode}"]);
    }

    /**
     * Check if a service is available.
     *
     * @param string $serviceName  Name of service
     * @param string|array $endpointSettingNames  Name of setting or array of setting names
     *
     * @return bool    True if the service is available
     */
    private function hasService($serviceName, $endpointSettingNames)
    {
        $found = false;
        if (!is_array($endpointSettingNames)) {
            $found = !empty($this->getSetting($endpointSettingNames));
        } else {
            foreach ($endpointSettingNames as $endpointSettingName) {
                $found = $found || !empty($this->getSetting($endpointSettingName));
            }
        }
        return $found || self::hasApiHook($serviceName, $this->getConsumer()->getFamilyCode());
    }

}

?>
