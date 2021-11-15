<?php
/**
 * NOTE: This class is auto generated by the swagger code generator program.
 * https://github.com/swagger-api/swagger-codegen
 * Do not edit the class manually.
 */

namespace SquareConnect\Model;

use \ArrayAccess;
/**
 * UpsertCatalogObjectResponse Class Doc Comment
 *
 * @category Class
 * @package  SquareConnect
 * @author   Square Inc.
 * @license  http://www.apache.org/licenses/LICENSE-2.0 Apache License v2
 * @link     https://squareup.com/developers
 */
class UpsertCatalogObjectResponse implements ArrayAccess
{
    /**
      * Array of property to type mappings. Used for (de)serialization 
      * @var string[]
      */
    static $swaggerTypes = array(
        'errors' => '\SquareConnect\Model\Error[]',
        'catalog_object' => '\SquareConnect\Model\CatalogObject',
        'id_mappings' => '\SquareConnect\Model\CatalogIdMapping[]'
    );
  
    /** 
      * Array of attributes where the key is the local name, and the value is the original name
      * @var string[] 
      */
    static $attributeMap = array(
        'errors' => 'errors',
        'catalog_object' => 'catalog_object',
        'id_mappings' => 'id_mappings'
    );
  
    /**
      * Array of attributes to setter functions (for deserialization of responses)
      * @var string[]
      */
    static $setters = array(
        'errors' => 'setErrors',
        'catalog_object' => 'setCatalogObject',
        'id_mappings' => 'setIdMappings'
    );
  
    /**
      * Array of attributes to getter functions (for serialization of requests)
      * @var string[]
      */
    static $getters = array(
        'errors' => 'getErrors',
        'catalog_object' => 'getCatalogObject',
        'id_mappings' => 'getIdMappings'
    );
  
    /**
      * $errors The set of [Error](#type-error)s encountered.
      * @var \SquareConnect\Model\Error[]
      */
    protected $errors;
    /**
      * $catalog_object The created [CatalogObject](#type-catalogobject).
      * @var \SquareConnect\Model\CatalogObject
      */
    protected $catalog_object;
    /**
      * $id_mappings The mapping between client and server IDs for this Upsert.
      * @var \SquareConnect\Model\CatalogIdMapping[]
      */
    protected $id_mappings;

    /**
     * Constructor
     * @param mixed[] $data Associated array of property value initializing the model
     */
    public function __construct(array $data = null)
    {
        if ($data != null) {
            if (isset($data["errors"])) {
              $this->errors = $data["errors"];
            } else {
              $this->errors = null;
            }
            if (isset($data["catalog_object"])) {
              $this->catalog_object = $data["catalog_object"];
            } else {
              $this->catalog_object = null;
            }
            if (isset($data["id_mappings"])) {
              $this->id_mappings = $data["id_mappings"];
            } else {
              $this->id_mappings = null;
            }
        }
    }
    /**
     * Gets errors
     * @return \SquareConnect\Model\Error[]
     */
    public function getErrors()
    {
        return $this->errors;
    }
  
    /**
     * Sets errors
     * @param \SquareConnect\Model\Error[] $errors The set of [Error](#type-error)s encountered.
     * @return $this
     */
    public function setErrors($errors)
    {
        $this->errors = $errors;
        return $this;
    }
    /**
     * Gets catalog_object
     * @return \SquareConnect\Model\CatalogObject
     */
    public function getCatalogObject()
    {
        return $this->catalog_object;
    }
  
    /**
     * Sets catalog_object
     * @param \SquareConnect\Model\CatalogObject $catalog_object The created [CatalogObject](#type-catalogobject).
     * @return $this
     */
    public function setCatalogObject($catalog_object)
    {
        $this->catalog_object = $catalog_object;
        return $this;
    }
    /**
     * Gets id_mappings
     * @return \SquareConnect\Model\CatalogIdMapping[]
     */
    public function getIdMappings()
    {
        return $this->id_mappings;
    }
  
    /**
     * Sets id_mappings
     * @param \SquareConnect\Model\CatalogIdMapping[] $id_mappings The mapping between client and server IDs for this Upsert.
     * @return $this
     */
    public function setIdMappings($id_mappings)
    {
        $this->id_mappings = $id_mappings;
        return $this;
    }
    /**
     * Returns true if offset exists. False otherwise.
     * @param  integer $offset Offset 
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return isset($this->$offset);
    }
  
    /**
     * Gets offset.
     * @param  integer $offset Offset 
     * @return mixed 
     */
    public function offsetGet($offset)
    {
        return $this->$offset;
    }
  
    /**
     * Sets value based on offset.
     * @param  integer $offset Offset 
     * @param  mixed   $value  Value to be set
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->$offset = $value;
    }
  
    /**
     * Unsets offset.
     * @param  integer $offset Offset 
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->$offset);
    }
  
    /**
     * Gets the string presentation of the object
     * @return string
     */
    public function __toString()
    {
        if (defined('JSON_PRETTY_PRINT')) {
            return json_encode(\SquareConnect\ObjectSerializer::sanitizeForSerialization($this), JSON_PRETTY_PRINT);
        } else {
            return json_encode(\SquareConnect\ObjectSerializer::sanitizeForSerialization($this));
        }
    }
}
