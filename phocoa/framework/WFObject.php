<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
/**
 * @package framework-base
 * @copyright Copyright (c) 2005 Alan Pinstein. All Rights Reserved.
 * @version $Id: kvcoding.php,v 1.3 2004/12/12 02:44:09 alanpinstein Exp $
 * @author Alan Pinstein <apinstein@mac.com>                        
 */

/** 
 * Base Class for all framework classes.
 *
 * Provides:
 *   - {@link KeyValueCoding}
 */
class WFObject
{
    function __construct()
    {
    }

    function valueForKey($key)
    {
        if ($key == NULL) throw( new Exception("NULL key Exception") );

        $performed = false;

        // try calling getter with naming convention "key"
        if (method_exists($this, $key))
        {
            $result = $this->$key();
            $performed = true;
        }

        // try calling getter with naming convention "getKey"
        if (!$performed)
        {
            $getterMethod = 'get' . ucfirst($key);
            if (method_exists($this, $getterMethod))
            {
                $result = $this->$getterMethod();
                $performed = true;
            }
        }

        // try accessing property directly
        if (!$performed)
        {
            $vars = get_object_vars($this);
            if (array_key_exists($key, $vars))
            {
                $result = $this->$key;
                $performed = true;
            }
        }

        if (!$performed)
        {
            throw( new Exception("Unknown key '$key' requested for object '" . get_class($this) . "'.") );
        }

        return $result;
    }

    function valueForKeyPath($keyPath)
    {
        if ($keyPath == NULL) throw( new Exception("NULL keyPath Exception") );

        // initialize
        $result = NULL;

        $keyParts = explode('.', $keyPath);
        $keyPartCount = count($keyParts);
        $modelKeyPath = NULL;
        if ($keyPartCount > 0)
        {
            $modelKeyPath = join('.', array_slice($keyParts, 1));
        }

        // walk keypath
        $keys = explode('.', $keyPath);
        $onFirstKey = true;
        foreach ($keys as $key) {
            // determine target
            if ($onFirstKey)
            {
                $target = $this;
            }
            else
            {
                $target = $result;
            }
            // target must be an object
            if (!is_object($target))
            {
                throw( new Exception('Target is not an object for keyPath.') );
            }

            $result = $target->valueForKey($key);

            // IF the result of the first key is an array, and the target of the first key was a WFObjectController (why this constraint? maybe to ensure that the array is of WFObjects?), AND there is more path,
            // THEN we should CREATE a new array with the results of calling EACH object in the array with the rest of the path.
            //if ($onFirstKey && ($target instanceof WFObjectController) && is_array($result) && !is_null($modelKeyPath))
            // Need to generalize this more, so that ANYTIME you hit an array, if you have path left, you simply create an array based on the rest of the path...
            // I think in the end, the magic system should work basically like this... if the result of any valueForKey() while traversing a keyPath is an array,
            // and there is still keyPath remaining to traverse, then it should create a magic array by having one entry for each object, with the value derived
            // by calling the remaining keyPath on each object.
            //
            // Finish up the above algorithm and write some tests!!!
            // 
            // I think the current implementation required the firstKey thing because there was no solution to "get the remaining modelKeyPath" -- maybe I was lazy?
            // I think the WFObjectController thing was there mostly b/c the original intended use was for ArrayControllers... remember ArrayControllers are WFObjectController subclasses.
            // The initially imagined use was $myArrayOfIDs = $arrayController->valueForKeyPath("arrangedObjects.id").
            // However, this is too specific, as there are other good and reasonable uses for the broader capabilities that still work for the above use case.
            if ($onFirstKey && is_array($result) && !is_null($modelKeyPath))
            {
                //print "magic on $keyPath<BR>";
                $magicArray = array();
                foreach ($result as $object) {
                    if (!is_object($object)) throw( new Exception("All array items must be OBJECTS THAT IMPLEMENT Key-Value Coding for KVC Magic Arrays to work.") );
                    if (!method_exists($object, 'valueForKey')) throw( new Exception("target is not Key-Value Coding compliant for valueForKey.") );
                    $magicArray[] = $object->valueForKey($modelKeyPath);
                }
                $result = $magicArray;
                break;
            }

            $onFirstKey = false;
        }

        return $result;
    }

    function setValueForKey($value, $key)
    {
        $performed = false;

        // try calling setter
        $setMethod = "set" . ucfirst($key);
        if (method_exists($this, $setMethod))
        {
            $this->$setMethod($value, $key);
            $performed = true;
        }

        if (!$performed)
        {
            // try accesing instance var directly
            $vars = get_object_vars($this);
            if (array_key_exists($key, $vars))
            {
                $this->$key = $value;
                $performed = true;
            }
        }

        if (!$performed)
        {
            throw( new Exception("Unknown key '$key' requested for object '" . get_class($this) . "'.") );
        }
    }

    /**
     * Helper function to convert a keyPath into the targetKeyPath (the object to call xxxKey on) and the targetKey (the key to call on the target object).
     * return array targetKeyPath, targetKey. Usage: list($targetKeyPath, $targetKey) = keyPathToParts($keyPath);
     */
    protected function keyPathToTargetAndKey($keyPath)
    {
        if ($keyPath == NULL) throw( new Exception("NULL key Exception") );

        // walk keypath
        // If the keypath is a.b.c.d then the targetKeyPath is a.b.c and the targetKey is d
        $keyParts = explode('.', $keyPath);
        $keyPartCount = count($keyParts);
        if ($keyPartCount == 0) throw( new Exception("Illegal keyPath: '{$keyPath}'. KeyPath must have at least one part.") );

        if ($keyPartCount == 1)
        {
            $targetKey = $keyPath;
            $target = $this;
        }
        else
        {
            $targetKey = $keyParts[$keyPartCount - 1];
            $targetKeyPath = join('.', array_slice($keyParts, 0, $keyPartCount - 1));
            $target = $this->valueForKey($targetKeyPath);
        }

        return array($target, $targetKey);
    }

    function setValueForKeyPath($value, $keyPath)
    {
        list($target, $targetKey) = $this->keyPathToTargetAndKey($keyPath);
        $target->setValueForKey($value, $targetKey);
    }

    /**
     * Validate the given value for the given keypath.
     *
     * This is the default implementation for this method. It looks for the target object based on the keyPath and then calls the validateValueForKey method.
     *
     * @param mixed A reference to value to check. Passed by reference so that the implementation can normalize the data.
     * @param string keyPath the keyPath for the value.
     * @param boolean A reference to a boolean. This value will always be FALSE when the method is called. If the implementation edits the $value, set to TRUE. This will resultion in setValueForKey() being called again with the new value.
     * @param object A WFError object describing the error.
     * @return boolean TRUE indicates a valid value, FALSE indicates an error.
     */
    function validateValueForKeyPath(&$value, $keyPath, &$edited, &$errors)
    {
        list($target, $targetKey) = $this->keyPathToTargetAndKey($keyPath);

        return $target->validateValueForKey($value, $targetKey, $edited, $errors);
    }

    /**
     * Validate the given value for the given key.
     *
     * Clients can normalize the value, and also report and error if the value is not valid.
     *
     * If the value is valid without modificiation, return TRUE and do not alter $edited or $error.
     * If the value is valid after being modified, return TRUE, and $edited to true.
     * IF the value is not valid, do not alter $value or $edited, but fill out the $error object with desired information.
     *
     * The default implementation (in WFObject) looks for a method named validate<key> and calls it, otherwise it returns TRUE. Here is the prototype:
     * <code>
     *      (boolean) function(&$value, &$edited, &$errors)
     * </code>
     *
     * @param mixed A reference to value to check. Passed by reference so that the implementation can normalize the data.
     * @param string keyPath the keyPath for the value.
     * @param boolean A reference to a boolean. This value will always be FALSE when the method is called. If the implementation edits the $value, set to TRUE.
     * @param array An array of WFError objects describing the error. The array is empty by default; you can add new error entries with:
     *      <code>
     *          $error[] = new WFError('My error message'); // could also add an error code (string) parameter.
     *      </code>
     * @return boolean TRUE indicates a valid value, FALSE indicates an error.
     */
    function validateValueForKey(&$value, $key, &$edited, &$errors)
    {
        $valid = true;

        // try calling validator
        $validatorMethod = 'validate' . ucfirst($key);
        if (method_exists($this, $validatorMethod))
        {
            $valid = $this->$validatorMethod($value, $edited, $errors);
        }

        if (!$valid)
        {
            WFLog::log("Errors: " . print_r($errors, true), WFLog::TRACE_LOG);
        }

        return $valid;
    }

    /* Print a description of the object.
     *
     * @return string A text description of the object.
     */
    function __toString()
    {
        return print_r($this, true);
    }
}
?>