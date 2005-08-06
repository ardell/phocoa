<?php

/* vim: set expandtab tabstop=4 shiftwidth=4: */
/**
 * @package WebApplication
 * @subpackage Module
 * @copyright Copyright (c) 2005 Alan Pinstein. All Rights Reserved.
 * @version $Id: kvcoding.php,v 1.3 2004/12/12 02:44:09 alanpinstein Exp $
 * @author Alan Pinstein <apinstein@mac.com>                        
 */

/**
 * Includes
 */
require_once('framework/WFPage.php');

/**
 * The WFModuleInvocation object is a wrapper around WFModule. This allows the modules to be nicely decoupled from the callers. Thus, the http handler
 * can create a WFModuleInvocation based on the URL, while a WFModuleView can create one based on parameters set by a caller.
 *
 * WFModuleInvocation is also used to keep track of "composited" pages. That is, pages can contain arbitrarily nested pages {@link WFModuleView}. This allows easy
 * creation of portal-type environments and promotes re-use of pages as components. Since most reusable components of web pages are more complicated than
 * single widgets (ie WFView or WFWidget subclasses), the ability to use modules as components allows for the creation of re-usable components that
 * harness the power of the Module/Page system. This way, components can use bindings, formatters, GUI builder, etc and thus make it much easier
 * for developers to build re-usable components for their applications.
 *
 * Developers may still want to develop new {@link WFWidget} and {@link WFView} subclasses, but these should be limited to their appropriate scope, which is
 * non-application specific UI widgets.
 * 
 * @todo Eventually we'd like the ability to have multiple BASE directories containing modules. Mainly the purpose of this is so that the framework could
 *       ship with some modules, and they would be accessible, but in a different place from the user's modules. We have to make the framework easy
 *       to update separately from the user code. We could have some "aliases" inside the path-walking stuff that would shunt over to another dir.
 *       for instance, maybe if the first path was "contrib" it would shunt over to contrib/modules/ or that kind of thing.
 * @todo Evaluate whether the WFModuleInvocation and WFModule should be coalesced into a single class... can they be used apart from each other?
 */
class WFModuleInvocation extends WFObject
{
    /**
     * @var object WFModuleInvocation A reference to the parent WFModuleInvocation for this object, or NULL if it's the root object.
     */
    protected $parentInvocation;
    /**
     * @var string the passed-in invocation path: /path/to/module[/Param1[/Param2]]
     */
    protected $invocationPath;
    /**
     * @var array the calculated extra parameters for the invocation: (Param1, Param2)
     */
    protected $invocationParameters;
    /**
     * @var string The path to the module. This will be normalized to always start WITHOUT '/'. IE: path/to/myModule
     */
    protected $modulePath;
    /**
     * @var string The name of the module that was found, if one was found, at modulePath.
     */
    protected $moduleName;
    /**
     * @var string The name of the page that will be displayed for this invocation.
     */
    protected $pageName;
    /**
     * @var object WFModule The WFModule that this invocation wraps.
     */
    protected $module;
    /**
      * @var boolean TRUE to have all forms for this module target the rood module, FALSE to target the current module.
      */
    protected $targetRootModule;
    /**
     * @var boolean TRUE if this invocation should respond to form submissions, FALSE otherwise. 
     *              This setting is typically used in situations where the module is used from a skin or a compositing situation.
     *              For instance, you may want to use a "search" module in your skin via {@link WFSkinModuleView}. When the user
     *              submits the form, it will automatically target the search module and display the results in the body. However,
     *              unless respondsToForms is set to FALSE, the skin itself will also respond to the search!
     */
    protected $respondsToForms;

    /**
     *  Constructor used to create a new WFModuleInvocation.
     *
     *  A WFModuleInvocation wraps the execution of a module. It contains all of the environment information needed to
     *  actually load and cause a module to execute and return the rendered result.
     *
     *  @param string The invocationPath for the module. The invocationPath is basically a way to specify the module to run, along with parameters.
     *                Example: path/to/my/module/pageName/param1/param2/paramN
     *  @param object The parent WFModuleInvocation that is creating this invocation, or NULL if this is the root invocation.
     *  @throws Various errors if the module could not be identified.
     */
    function __construct($invocationPath, $parentInvocation)
    {
        parent::__construct();

        $this->invocationPath = ltrim($invocationPath, '/');
        $this->parentInvocation = $parentInvocation;

        $this->targetRootModule = true;
        $this->invocationParameters = array();
        $this->modulePath = NULL;
        $this->moduleName = NULL;
        $this->pageName = NULL;
        $this->module = NULL;

        $this->respondsToForms = true;  // modules respond to forms by default
    }

    /**
     *  Should this invocation of this module respond to forms?
     *
     *  @return boolean TRUE to respond to the form, FALSE otherwise.
     *  @see WFModuleInvocation::respondsToForms
     */
    function respondsToForms()
    {
        return $this->respondsToForms;
    }

    /**
     *  Set whether or not the module in this invocation should respond to forms.
     *
     *  @param boolean TRUE to respond to the form, FALSE otherwise.
     *  @see WFModuleInvocation::respondsToForms
     */
    function setRespondsToForms($responds)
    {
        $this->respondsToForms = $responds;
    }

    /**
     *  Get the parent invocation.
     *
     *  @return object WFModuleInvocation The WFModuleInvocation for the parent, or NULL if this is the root invocation.
     */
    function parentInvocation()
    {
        return $this->parentInvocation;
    }

    /**
     *  Get the root invocation for this invocation. This may be the current invocation.
     *
     *  @return object WFModuleInvocation that is the "root" of this invocation tree.
     */
    function rootInvocation()
    {
        $root = $this;
        while (true) {
            if ($root->parentInvocation() == NULL) break;
            $root = $root->parentInvocation();
        }
        return $root;
    }

    /**
     *  Does this invocation target the root invocation's module, or the current module?
     *
     *  @return boolean TRUE if it targets the root module, false if it targets the current module.
     */
    function targetRootModule()
    {
        return $this->targetRootModule;
    }

    /**
     *  Should this invocation target the root invocation's module, or the current module?
     *  
     *  For modules that target the root module, their forms will always post to the root module.
     *  This will result in keeping the current "compositing" of the root module.
     *  
     *  For modules that target the current module, their forms will always post to the current module.
     *  This will result in making the sub-module the root module if a form is submitted.
     * 
     *  @param boolean TRUE if it targets the root module, false if it targets the current module.
     */
    function setTargetRootModule($targetRoot)
    {
        $this->targetRootModule = $targetRoot;
    }

    /**
     *  Get the module that this invocation wraps.
     *
     *  @return object WFModule The WFModule wrapped by this invocation.
     */
    function module()
    {
        return $this->module;
    }

    /**
     *  Get the name of the module that this invocation is wrapping.
     *
     *  @return string Module name.
     */
    function moduleName()
    {
        return $this->moduleName;
    }

    /**
     *  Get the name of the page that this invocation will call in the module.
     *
     *  @return string Page name.
     */
    function pageName()
    {
        return $this->pageName;
    }

    /**
     *  Get the module path to the current module.
     *
     *  Will always be normalized to the form: path/to/myModule
     *
     *  @return string Module path.
     */
    function modulePath()
    {
        return $this->modulePath;
    }

    /**
     *  Get the invocationPath that was used to create this WFModuleInvocation.
     *
     *  @return string The complete invocationPath for this WFModuleInvocation.
     */
    function invocationPath()
    {
        return $this->invocationPath;
    }

    /**
     *  Is this module the root invocation?
     *
     *  @return boolean TRUE if this is the root invocation, FALSE otherwise.
     *  @todo Should this be named isRootModule? This would be more consistent with setTargetRootModule/targetRootModule.
     */
    function isRootInvocation()
    {
        return ($this->parentInvocation == NULL);
    }

    /**
     *  Get the parameters that were provided in the invocationPath.
     *
     *  @return array The parameters extracted from the invocationPath.
     */
    function parameters()
    {
        return $this->invocationParameters;
    }

    /**
     *  Get the parameters that were provided in the invocationPath, as a '/'-separated string.
     *
     *  @return string The invocation parameters in the form: /param1/param2. Each param will be urlencoded.
     */
    function parametersAsPathInfo()
    {
        $path = '';
        foreach ($this->invocationParameters as $p) {
            $path .= '/' . urlencode($p);
        }
        return $path;
    }

    /**
     *  Execute the module wrapped by this invocation.
     *
     *  This function is where the invocationPath is parsed, the WFModule instantiated, and the module executed.
     *
     *  @return string The rendered result of the module invocation.
     *  @throws Any uncaught exception.
     */
    function execute()
    {
        // set up module
        $this->extractComponentsFromInvocationPath();

        // execute
        // initialize the request page
        $this->module->requestPage()->initPage($this->pageName);

        // if the responsePage wasn't inited already, then we assume we're going to just display the same page.
        if (!$this->module->responsePage())
        {
            $this->module->setupResponsePage();
        }

        // render
        return $this->module->responsePage()->render();
    }

    /**
     *  Parses the invocationPath, looks for the module, instantiates the module, etc.
     *
     *  This is where {@link WFAuthorizationManager module security} is applied. If the page requies login to continue, the system will redirect to a login page,
     *  and will redirect back to the initial page upon successful login.
     *
     *  @throws Various exceptions in setting up the module.
     */
    private function extractComponentsFromInvocationPath()
    {
        // walk path looking for the module.
        $pathInfoParts = preg_split('/\//', trim($this->invocationPath, '/'), -1, PREG_SPLIT_NO_EMPTY);

        //print_r($pathInfoParts);
        //print "URI: $<BR>";
        $modulesDirPath = WFWebApplication::appDirPath(WFWebApplication::DIR_MODULES);
        $modulePath = '';
        $partsUsedBeforeModule = 0;
        foreach ($pathInfoParts as $part) {
            $modulePath .= '/' . $part;
            $possibleModuleFilePath = $modulesDirPath . $modulePath . '/' . $part . '.php';
            //print "Testing $possibleModuleFilePath to see if it's a module file.<BR>";
            if (file_exists($possibleModuleFilePath))
            {
                $this->modulePath = ltrim($modulePath, '/');
                $this->moduleName = $pathInfoParts[$partsUsedBeforeModule];
                if (isset($pathInfoParts[$partsUsedBeforeModule + 1]))
                {
                    $this->pageName = $pathInfoParts[$partsUsedBeforeModule + 1];
                }
                if (count($pathInfoParts) > 2)
                {
                    $this->invocationParameters = array_slice($pathInfoParts, $partsUsedBeforeModule + 2);
                }
                //print "Found module {$this->moduleName} in {$this->modulePath}.";
                //if ($this->pageName) print " Found page name: {$this->pageName}";
                //print "<BR>";
                //print "PATH_INFO: {$this->invocationParameters}<BR>";
                break;
            }
            else if (is_dir($modulesDirPath . '/' . $modulePath))
            {
                $partsUsedBeforeModule++;
            }
            else
            {
                throw( new Exception("Module 404: invocation path {$this->invocationPath} could not be found.") );
            }
        }

        if (empty($this->moduleName) or empty($this->pageName))
        {
            $needsRedirect = true;
        }
        else
        {
            $needsRedirect = false;
        }

        if (empty($this->moduleName)) 
        {
            // get default from application config
            $app = WFWebApplication::sharedWebApplication();
            $defaultModulePath = $app->defaultModule();
            $this->modulePath = ltrim($defaultModulePath, '/');
            $this->moduleName = basename($defaultModulePath);
        }

        if (empty($this->moduleName)) throw( new Exception("Module 404: No module name could be determined from {$this->invocationPath}.") );

        // if we get here, we're guaranteed that a modulePath is valid.
        // load module instance
        try {
            $this->module = WFModule::factory($this);

            // check security, but only for the root invocation
            if ($this->isRootInvocation())
            {
                $authInfo = WFAuthorizationManager::sharedAuthorizationManager()->authorizationInfo();
                $access = $this->module->checkSecurity($authInfo);
                if (!in_array($access, array(WFAuthorizationManager::ALLOW, WFAuthorizationManager::DENY))) throw( new Exception("Unexpected return code from checkSecurity.") );
                // if access is denied, see if there is a logged in user. If so, then DENY. If not, then allow login.
                if ($access == WFAuthorizationManager::DENY)
                {
                    if ($authInfo->isLoggedIn())
                    {
                        // if no one is logged in, allow login, otherwise deny.
                        throw( new WFAuthorizationException("Access denied.", WFAuthorizationException::DENY) );
                    }
                    else
                    {
                        // if no one is logged in, allow login, otherwise deny.
                        throw( new WFAuthorizationException("Try logging in.", WFAuthorizationException::TRY_LOGIN) );
                    }
                }
            }
        } catch (WFAuthorizationException $e) {
            switch ($e->getCode()) {
                case WFAuthorizationException::TRY_LOGIN:
                    // NOTE: we pass the redir-url base64 encoded b/c otherwise Apache picks out the slashes!!!
                    header("Location: " . WFRequestController::WFURL('login', 'promptLogin') . '/' . base64_encode(WWW_ROOT . '/' . $this->invocationPath));
                    exit;
                    break;
                case WFAuthorizationException::DENY:
                    header("Location: " . WFRequestController::WFURL('login', 'notAuthorized'));
                    exit;
                    break;
            }
        } catch (Exception $e) {
            die($e->__toString());
        }

        // determine default page
        if (empty($this->pageName))
        {
            $this->pageName = $this->module->defaultPage();
        }
        if (empty($this->pageName)) throw( new Exception("No page could be determined. Make sure you are supplying an page in the invocation path or have your module supply a defaultPage.") );

        // was there a form submitted? If so, we need to determine if WE are the target of that form, and if so, override some data
        // this may not be needed anymore now that the action param pulls its info from the invocation path
//        if (!empty($_REQUEST['__formName']))
//        {
//            if (empty($_REQUEST['__currentModule'])) throw( new Exception("Form submitted contains no __currentModule info.") );
//
//            // is this invocation the target of the form?
//            if ($_REQUEST['__currentModule'] == $this->modulePath)
//            {
//                // let page name from FORM override the URL page name; happens when actions change the form.
//                if (!empty($_REQUEST['__currentPage']))
//                {
//                    $this->pageName = $_REQUEST['__currentPage'];
//                }
//            }
//        }

        // redirect as needed - this doesn't make sense inside of WFModuleInvocation...
        // of course cannot have invocationParameters from invocationPath unless module and pageName are specified
        if ($needsRedirect)
        {
            if ($this->isRootInvocation())
            {
                header('Location: ' . WFRequestController::WFURL($this->modulePath, $this->pageName));
                exit;
            }
            else
            {
                throw( new Exception("You must specify a complete invocationPath.") );
            }
        }
    }

}

/**
  * The WFModule represents a single chunk of web application functionality.
  * 
  * Each module can have multiple pages, and each page can have multiple actions.
  * 
  * Basially there are two types of requests; page load requests, and actions 
  * against loaded pages.
  *
  * A url like /myModule/myPage will cause the framework to simply load the 
  * requested page. Extra PATH_INFO data may be used to load particular model 
  * data into the page.
  *
  *  Examples:
  *     Open up a product detail page with /admin/productDetail/123
  *     Open up a list of all products with /admin/productList
  *     Show page 2 of the search results with /admin/productSearch/myQuery/2
  *
  * When the ResponsePage finishes initializing / loading the page, your module
  * will be called back on the method <pageName>_PageDidLoad($page), which is your 
  * opportunity to provide default information and/or perform changes to the UI 
  * before it is rendered.
  *
  * A url like /myModule/myPage?__action=myAction will restore the state of the 
  * myPage UI to the submitted state, then call the myAction handler for that page. 
  * Of course myAction must be part of the myPage. Also, the myAction handler may 
  * decide to CHANGE the current page.
  *
  *  Examples:
  *     Edit a product with /admin/productDetail/123?__action=edit or /admin/productDetail?__action=edit&id=123
  *     Delete a product with /admin/productList?__action=delete&id=123
  *
  * Action methods have the prototype: <pageName>_<actionName>_Action($requestPage) 
  * where $requestPage is restored UI state of the calling page (pageName).
  *
  * Each module has a request and a repsonse page. The framework will restore 
  * the request's UI state before calling your module's action handler so that
  * you can easily access widget values via the framework instead of via the $_REQUEST vars.
  *
  * Once you decide which WFPage to display as the response, the framework loads 
  * all of the widgets in the page and allows you to manipulate them from your 
  * action handler before rendering the response.
  *
  */
abstract class WFModule extends WFObject
{
    /**
     * @var object The WFPage for the incoming request.
     */
    protected $requestPage;
    /**
     * @var object The WFPage for the outgoing response.
     */
    protected $responsePage;
    /**
     * @var array An associative array of all shared instances for this module.
     */
    protected $__sharedInstances;
    /**
      * @var object The WFModuleInvocation that launched this module.
      */
    protected $invocation;

    /**
      * Constructor.
      *
      * @param string The relative path to this module.
      */
    function __construct($invocation)
    {
        parent::__construct();

        if (!($invocation instanceof WFModuleInvocation)) throw( new Exception("Modules must be instantiated with a WFModuleInvocation.") );
        $this->invocation = $invocation;

        // load shared instances
        $this->prepareSharedInstances();

        // set up pages
        $this->requestPage = new WFPage($this);
        $this->responsePage = NULL;
    }

    /**
      * Allow the module to check security.
      *
      * @param object WFAuthorizationInfo The authInfo for the current user.
      * @return integer One of WFAuthorizationManager::ALLOW or WFAuthorizationManager::DENY.
      */
    function checkSecurity(WFAuthorizationInfo $authInfo)
    {
        return WFAuthorizationManager::ALLOW;
    }

    /**
      * Generate a "full" URL to the given module / page.
      *
      * It is recommended to use this function to generate all URL's to pages in the application.
      * Of course you may append some PATH_INFO or params afterwards.
      * Also it is recommended that when referencing another action in the SAME module you pass NULL for the module,
      * as this will ensure that your links don't break if you decide to rename your module.
      *
      * @param string The module name (or NULL to use CURRENT module).
      * @param string The page name (or NULL to use CURRENT page).
      * @return string a RELATIVE URL to the requested module/page.
      */
    function WFURL($module = NULL, $page = NULL)
    {
        $module = ltrim($module, '/');  // just in case a '/path' path is passed, we normalize it for our needs.
        if (empty($module))
        {
            $module = $this->invocation->modulePath();
        }
        if (empty($page))
        {
            $page = $this->invocation->pageName();
        }
        $url = WWW_ROOT . '/' . $module . '/' . $page;
        return $url;
    }


    /**
      * Get the module invocation.
      * 
      * @return object The WFModuleInvocation object that owns this module instance.
      */
    function invocation()
    {
        return $this->invocation;
    }

    /**
      * Get the module's name
      *
      * @return string The module's name.
      */
    function moduleName()
    {
        return get_class($this);
    }

    /**
      * Get the path to the given page.
      *
      * @param string The page name.
      * @return string The path to the page, without extension. Add '.instances', '.config', or '.tpl'.
      */
    function pathToPage($pageName)
    {
        return $this->pathToModule() . '/' . $pageName;
    }

    /**
     *  Get the path to the module.
     *
     *  @return string The absolute file system path to the module's directory.
     */
    function pathToModule()
    {
        $modDir = WFWebApplication::appDirPath(WFWebApplication::DIR_MODULES);
        return $modDir . '/' . $this->invocation->modulePath();
    }

    /**
      * Get a module instance for the specified module path.
      * 
      * @param string The path to the module.
      * @return object A WFModule subclass instance.
      * @throws Exception if the module subclass or file does not exist.
      * @throws WFAuthorizationException if there is an access control violation for the module.
      */
    function factory($invocation)
    {
        $moduleName = $invocation->moduleName();
        $modulesDirPath = WFWebApplication::appDirPath(WFWebApplication::DIR_MODULES);
        $moduleFilePath = $modulesDirPath . '/' . $invocation->modulePath() . '/' . $moduleName . '.php';

        // load module subclass and instantiate
        require_once($moduleFilePath);
        if (!class_exists($moduleName)) throw( new Exception("WFModule subclass {$moduleName} does not exist.") );
        $module = new $moduleName($invocation);

        return $module;
    }

    /**
     * Prepare any declared shared instances for the module.
     *
     * Shared Instances are objects that not WFView subclasses. Only WFView subclasses may be instantiated in the <pageName>.instances files.
     * The Shared Instances mechanism is used to instantiate any other objects that you want to use for your pages. Usually, these are things
     * like ObjectControllers or Formatters, which are typically "shared" across multiple pages. The Shared Instances mechanism makes it
     * easy to instantiate and configure the properties of objects without coding, and have these objects accessible for bindings or properties.
     * Of course, you can instantiate objects yourself and use them programmatically. This is just a best-practice for a common situation.
     *
     * The shared instances mechanism simply looks for a shared.instances and a shared.config file in your module's directory. The shared.instances
     * file should simply have a var $__instances that is an associative array of 'unique id' => 'className'. For each declared instance, the
     * module's instance var $this->$uniqueID will be set to a new instance of "className".
     *
     * <code>
     *   $__instances = array(
     *       'instanceID' => 'WFObjectController',
     *       'instanceID2' => 'WFUnixDateFormatter'
     *   );
     * </code>
     *
     * To bind to a shared instance (or for that matter any object that's an instance var of the module), set the instanceID to "#module#,
     * leave the controllerKey blank, and set the modelKeyPath to "<instanceVarName>.rest.of.key.path".
     *
     * To use a shared instance as a property, .................... NOT YET IMPLEMENTED.
     * 
     *
     * @todo Allow properties of page.config files to use shared instances.
     */
    function prepareSharedInstances()
    {
        $app = WFWebApplication::sharedWebApplication();
        $modDir = $app->appDirPath(WFWebApplication::DIR_MODULES);
        $instancesFile = $modDir . '/' . $this->invocation->modulePath() . '/shared.instances';
        $configFile = $modDir . '/' . $this->invocation->modulePath() . '/shared.config';

        if (!file_exists($instancesFile)) return;
        $moduleInfo = new ReflectionObject($this);
        include($instancesFile);
        foreach ($__instances as $id => $class) {
            // enforce that the instance variable exists
            try {
                $moduleInfo->getProperty($id);
            } catch (Exception $e) {
                WFLog::log("shared.instances:: Module '" . get_class($this) . "' does not have property '$id' declared.", WFLog::WARN_LOG);
            }

            // instantiate, keep reference in shared instances
            $this->__sharedInstances[$id] = $this->$id = new $class;
        }

        // configure the new instances
        $this->loadConfig($configFile);

        // call the sharedInstancesDidLoad() callback
        $this->sharedInstancesDidLoad();
    }

    /**
     * Optional method for WFModule subclasses if they want to know when the shared instances have finished loading.
     *
     * Will be called after the Shared Instances have been instantiated and configured, and before any pages are loaded.
     */
    function sharedInstancesDidLoad() {}

    /**
     * Load the shared.config file for the module and process it.
     *
     * The shared.config file is an OPTIONAL component.
     * If your module has no instances, or the instances don't need configuration, you don't need a shared.config file.
     *
     * The shared.config file can only configure properties of objects at this time.
     * Only primitive value types may be used. String, boolean, integer, double, NULL. NO arrays or objects allowed.
     *
     * <code>
     *   $__config = array(
     *       'instanceID' => array(
     *           'properties' => array(
     *              'propName' => 'Property Value',
     *              'propName2' => 123
     *           )
     *       ),
     *       'instanceID2' => array(
     *           'properties' => array(
     *              'propName' => 'Property Value',
     *              'propName2' => true
     *           )
     *       )
     *   );
     * </code>
     *
     * @param string The absolute path to the config file.
     * @throws Various errors if configs are encountered for for non-existant instances, etc. A properly config'd page should never throw.
     */
    protected function loadConfig($configFile)
    {
        // be graceful; if there is no config file, no biggie, just don't load config!
        if (!file_exists($configFile)) return;

        include($configFile);
        foreach ($__config as $id => $config) {
            WFLog::log("loading config for id '$id'", WFLog::TRACE_LOG);
            // get the instance to apply config to
            if (!isset($this->$id)) throw( new Exception("Couldn't find shared instance with ID '$id' to configure.") );
            $configObject = $this->$id;

            // atrributes
            if (isset($config['properties']))
            {
                foreach ($config['properties'] as $keyPath => $value) {
                    switch (gettype($value)) {
                        case "boolean":
                        case "integer":
                        case "double":
                        case "string":
                        case "NULL":
                            // these are all OK, fall through
                            break;
                        default:
                            throw( new Exception("Config value for shared instance id::property '$id::$keyPath' is not a vaild type (" . gettype($value) . "). Only boolean, integer, double, string, or NULL allowed.") );
                            break;
                    }
                    WFLog::log("SharedConfig:: Setting '$id' property, $keyPath => $value", WFLog::TRACE_LOG);
                    $configObject->setValueForKeyPath($value, $keyPath);
                }
            }
        }
    }

    /**
     * Call this method if you want the ResponsePage to be the same as the RequestPage.
     *
     * This is a convenience method for actions that don't need to switch the page.
     */
    function setupResponsePage($pageName = NULL)
    {
        if (is_null($pageName) or ($this->requestPage->pageName() == $pageName))
        {
            //print "using request page for response<br>";
            $this->responsePage = $this->requestPage;
        }
        else
        {
            //print "using $pageName for response page<br>";
            $this->responsePage = new WFPage($this);
            $this->responsePage->initPage($pageName);
        }
    }

    /**
     *  Get the responsePage for the module.
     *
     *  @return object The WFPage representing the responsePage for this module.
     */
    function responsePage()
    {
        return $this->responsePage;
    }

    /**
     *  Get the requestPage for the module.
     *
     *  @return object The WFPage representing the requestPage for this module.
     */
    function requestPage()
    {
        return $this->requestPage;
    }

    /**
     * Get the default page to use for the module if no page is specified.
     * @return string The name of the default page.
     */
    abstract function defaultPage();
}

?>