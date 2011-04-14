<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
/**
 * @package framework-base
 * @subpackage Error
 * @copyright Copyright (c) 2005 Alan Pinstein. All Rights Reserved.
 * @version $Id: kvcoding.php,v 1.3 2004/12/12 02:44:09 alanpinstein Exp $
 * @author Alan Pinstein <apinstein@mac.com>                        
 */

/** 
 * A generic error class for a single error.
 */
class WFError extends WFObject
{
    protected $errorMessage;
    protected $errorCode;

    function __construct($errorMessage = NULL, $errorCode = NULL)
    {
        parent::__construct();
        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
    }

    function setErrorMessage($msg)
    {
        $this->errorMessage = $msg;
    }
    function errorMessage()
    {
        return $this->errorMessage;
    }

    function setErrorCode($code)
    {
        $this->errorCode = $code;
    }
    function errorCode()
    {
        return $this->errorCode;
    }
    function __toString()
    {
        return "Error #{$this->errorCode}: {$this->errorMessage}";
    }
    
}

/**
 * An interface for accessing collections of WFErrors.
 *
 * NOTE: In any controller that catches WFErrorCollection (or WFErrorsException), the controller should always re-throw the exception after processing it (typically with propagateErrorsForKeyToWidget).
 *
 * @see WFErrorsException
 * @see WFErrorArray
 * @see WFPropelException
 */
interface WFErrorCollection
{
    /**
     * Get a WFErrorArray with all errors managed by the interface.
     *
     * @return object WFErrorArray
     */
    public function errors();

    /**
     * Get all errors, flattened. This removes the key association of the WFError objects.
     *
     * @return array An array of {@link WFError}.
     */
    public function allErrors();

    /**
     * Get all errors that are not associated with any key.
     *
     * @return array An array of {@link WFError}.
     */
    public function generalErrors();

    /**
     * Add an error that is not associated with a key.
     *
     * @param object WFError A WFError object to add.
     * @return WFErrorCollection This error collection object (for fluent interface).
     */
    public function addGeneralError($error);

    /**
     * Get all errors for a particular key.
     *
     * @return array An array of {@link WFError}.
     */
    public function errorsForKey($key);

    /**
     * Add an error for a particular key
     *
     * Note for implementers: Make sure to check whether
     * the key exists in the error collection.
     *
     * @param object WFError A WFError object to add.
     * @param string The key to add the error to.
     * @return WFErrorCollection This error collection object (for fluent interface).
     */
    public function addErrorForKey($error, $key);

    /**
     * Are there any errors?
     *
     * @return boolean
     */
    public function hasErrors();

    /**
     * Are there any errors for a particular key?
     *
     * @param string The key to look for errors in.
     * @return boolean
     */
    public function hasErrorsForKey($key);

    /**
     * Checks for a certain error code across all errors.
     *
     * @param mixed The error code to look for.
     * @return boolean
     */
    public function hasErrorWithCode($code);

    /**
     * Checks for a certain error code in the errors for a certain key.
     *
     * @param mixed The error code to look for.
     * @param string The key to look for errors in.
     * @return boolean
     */
    public function hasErrorWithCodeForKey($code, $key);
}

/**
 * WFErrorArray class can be used in lieu of array() for passing into KVC functions as the $errors parameter.
 *
 * WFErrorArray knows how to handle the multi-level error structure used by {@link WFKeyValueCoding::validateObject()}.
 *
 * <code>
 * array(
 *     // errors generated from interproperty validation
 *     object WFError,
 *     object WFError,
 *     // errors generated by validateName
 *     'name' => array(
 *          object WFError
 *     ),
 *     // password generated by validateName
 *     'password' => array(
 *          object WFError,
 *          object WFError,
 *          object WFError
 *     )
 * )
 * </code>
 *
 * Using WFErrorArray instead of a standard error will allow you to use the $errors array as an object and interrogate it
 * for things like particular error codes, all errors for a certain property, etc.
 */
class WFErrorArray extends WFArray implements WFErrorCollection
{
    public function __construct($array = array(), $flags = 0, $iterator_class = "ArrayIterator")
    {
        parent::__construct($array, $flags, $iterator_class);
    }

    public function errors()
    {
        return $this;
    }

    public function hasErrorWithCode($code)
    {
        foreach ($this->allErrors() as $e)
        {
            if ($e->errorCode() == $code) return true;
        }
        return false;
    }

    public function hasErrorWithCodeForKey($code, $key)
    {
        if (!$this->hasErrorsForKey($key)) return false;

        $errorCodesForKey = WFArray::arrayWithArray($this->errorsForKey($key))->map('errorCode');
        return in_array($code, $errorCodesForKey);
    }

    public function hasErrors()
    {
        return (count($this) > 0);
    }

    public function hasErrorsForKey($key)
    {
        return isset($this[$key]);
    }

    public function allErrors()
    {
        $flattenedErrors = array();
        foreach ($this as $k => $v) {
            if (gettype($k) == 'integer')
            {
                $flattenedErrors[] = $v;
            }
            else
            {
                $flattenedErrors = array_merge($flattenedErrors, $v);
            }
        }
        return $flattenedErrors;
    }

    /**
     * Get the errors that are not mapped to specific properties.
     *
     * @return array An array of WFError objects.
     */
    public function generalErrors()
    {
        $general = array();
        foreach ($this as $k => $v) {
            if (gettype($k) == 'integer')
            {
                $general[] = $v;
            }
        }
        return $general;
    }

    /**
     * Add an error that is not associated with a key.
     *
     * @param object WFError A WFError object to add.
     * @return WFErrorArray This error collection object (for fluent interface).
     */
    public function addGeneralError($error)
    {
        $this[] = $error;
        return $this;
    }

    /**
     * Get all errors for the given key.
     *
     * @return array An array of all WFError objects.
     */
    public function errorsForKey($key)
    {
        if (isset($this[$key]))
        {
            return $this[$key];
        }
        return array();
    }

    /**
     * Add an error for a particular key
     *
     * Note for implementers: Make sure to check whether
     * the key exists in the error collection.
     *
     * @param object WFError A WFError object to add.
     * @param string The key to add the error to.
     * @return WFErrorArray This error collection object (for fluent interface).
     */
    public function addErrorForKey($error, $key)
    {
        if (!isset($this[$key]))
        {
            $this[$key] = array();
        }

        $this[$key][] = $error;
        return $this;
    }

    /**
     * Get all error codes for the given key.
     *
     * @return array An array all codes for the WFError objects for the given key.
     */
    public function errorCodesForKey($key)
    {
        $codes = array();
        foreach ($this->errorsForKey($key) as $e) {
            $codes[] = $e->errorCode();
        }
        return $codes;
    }

    public function __toString()
    {
        $str = "";
        foreach ($this->generalErrors() as $e) {
            $str .= $e->errorCode() . ' - ' . $e->errorMessage() . "\n";
        }
        foreach ($this as $k => $v) {
            if (gettype($k) == 'integer') continue;
            $str .= "Errors for key: {$k}\n";
            $keyErrs = new WFErrorArray($v);
            $str .= $keyErrs;
        }
        if ($str === "")
        {
            $str = "(no errors)";
        }
        return $str;
    }

    /**
     * Convenience method to allow for a fluent interface.
     * e.g...
     *
     * WFConcreteErrorCollection::create()
     *      ->addGeneralError(...);
     */
    public static function create()
    {
        return new self();
    }

}

/**
 * A special WFException subclass meant for carrying multiple WFError objects.
 *
 * WFPage automatically catches WFErrorsException's thrown from action methods and displays the errors.
 *
 * WFErrorsException knows how to handle the multi-level error structure used by {@link WFKeyValueCoding::validateObject()}.
 * @see WFErrorArray
 */
class WFErrorsException extends WFException implements WFErrorCollection
{
    protected $errors;

    function __construct($errors = array())
    {
        if (!is_array($errors) and !($errors instanceof WFErrorArray)) throw( new WFException("WFErrorsException requires an array of WFError objects, was passed: " . $errors) );

        if (!($errors instanceof WFErrorArray))
        {
            $errors = new WFErrorArray($errors);
        }
        $this->errors = $errors;

        $message = join(',', $this->errors->valueForKeyPath('allErrors.errorMessage'));
        parent::__construct($message);
    }

    /**
     * Get all error codes for the given key.
     *
     * @return array An array all codes for the WFError objects for the given key.
     * @deprecated Use {@link WFErrorsException::hasErrorWithCodeForKey()}
     */
    public function errorCodesForKey($key)
    {
        WF_LOG_DEPRECATED && WFLog::deprecated("WFErrorsException::errorCodesForKey() is deprecated. Use WFErrorsException::hasErrorWithCodeForKey().");

        return $this->errors->errorCodesForKey($key);
    }

    /**
     * Inform a widget of all errors for the given key.
     *
     * Optionally [and by default], prune the errors that have been propagated from the current list. Since the caller will typically re-throw this exception to be caught by the WFPage,
     * the auto-pruning prevents errors from appearing twice, as the WFPage will automatically detect and report all errors as well (although not linked to widgets).
     *
     * @param string The key which generated the errors
     * @param object WFWidget The widget that the errors should be reported to.
     * @param bolean Prune errors for this key from the exception object.
     */
    public function propagateErrorsForKeyToWidget($key, $widget, $prune = true)
    {
        WF_LOG_DEPRECATED && WFLog::deprecated("WFErrorsException::propagateErrorsForKeyToWidget() is deprecated. Use WFPage::propagateErrorsForKeysToWidgets() or WFPage::propagateErrorsForKeyToWidget().");

         foreach ($this->errorsForKey($key) as $keyErr) {
             $widget->addError($keyErr);
         }
         if ($prune && isset($this->errors[$key]))
         {
             unset($this->errors[$key]);
         }
    }

    public function __toString()
    {
        return "WFErrorsException with errors: " . $this->errors;
    }

    /***************** WFErrorCollection Interface Pass-Thru ********************/
    public function errors()
    {
        return $this->errors;
    }

    public function generalErrors()
    {
        return $this->errors->generalErrors();
    }

    /**
     * Add an error that is not associated with a key.
     *
     * @param object WFError A WFError object to add.
     * @return WFErrorsException This error collection object (for fluent interface).
     */
    public function addGeneralError($error)
    {
        $this->errors[] = $error;
        return $this;
    }

    public function allErrors()
    {
        return $this->errors->allErrors();
    }

    public function errorsForKey($key)
    {
        return $this->errors->errorsForKey($key);
    }

    /**
     * Add an error for a particular key
     *
     * Note for implementers: Make sure to check whether
     * the key exists in the error collection.
     *
     * @param object WFError A WFError object to add.
     * @param string The key to add the error to.
     * @return WFErrorCollection This error collection object (for fluent interface).
     */
    public function addErrorForKey($error, $key)
    {
        if (!isset($this->errors[$key]))
        {
            $this->errors[$key] = array();
        }

        $this->errors[$key][] = $error;
        return $this;
    }

    public function hasErrors()
    {
        return $this->errors->hasErrors();
    }

    public function hasErrorsForKey($key)
    {
        return $this->errors->hasErrorsForKey($key);
    }

    public function hasErrorWithCode($code)
    {
        return $this->errors->hasErrorWithCode($code);
    }

    public function hasErrorWithCodeForKey($code, $key)
    {
        return $this->errors->hasErrorWithCodeForKey($code, $key);
    }

    /**
     * Convenience method to allow for a fluent interface.
     * e.g...
     *
     * WFConcreteErrorCollection::create()
     *      ->addGeneralError(...);
     */
    public static function create()
    {
        return new self(new WFErrorArray);
    }

}
