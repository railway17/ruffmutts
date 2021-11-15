<?php
/**
 * NOTE: This class is auto generated by the swagger code generator program.
 * https://github.com/swagger-api/swagger-codegen
 * Do not edit the class manually.
 */

namespace SquareConnect\Model;

use \ArrayAccess;
/**
 * SearchOrdersRequest Class Doc Comment
 *
 * @category Class
 * @package  SquareConnect
 * @author   Square Inc.
 * @license  http://www.apache.org/licenses/LICENSE-2.0 Apache License v2
 * @link     https://squareup.com/developers
 */
class SearchOrdersRequest implements ArrayAccess
{
    /**
      * Array of property to type mappings. Used for (de)serialization 
      * @var string[]
      */
    static $swaggerTypes = array(
        'location_ids' => 'string[]',
        'cursor' => 'string',
        'query' => '\SquareConnect\Model\SearchOrdersQuery',
        'limit' => 'int',
        'return_entries' => 'bool'
    );
  
    /** 
      * Array of attributes where the key is the local name, and the value is the original name
      * @var string[] 
      */
    static $attributeMap = array(
        'location_ids' => 'location_ids',
        'cursor' => 'cursor',
        'query' => 'query',
        'limit' => 'limit',
        'return_entries' => 'return_entries'
    );
  
    /**
      * Array of attributes to setter functions (for deserialization of responses)
      * @var string[]
      */
    static $setters = array(
        'location_ids' => 'setLocationIds',
        'cursor' => 'setCursor',
        'query' => 'setQuery',
        'limit' => 'setLimit',
        'return_entries' => 'setReturnEntries'
    );
  
    /**
      * Array of attributes to getter functions (for serialization of requests)
      * @var string[]
      */
    static $getters = array(
        'location_ids' => 'getLocationIds',
        'cursor' => 'getCursor',
        'query' => 'getQuery',
        'limit' => 'getLimit',
        'return_entries' => 'getReturnEntries'
    );
  
    /**
      * $location_ids The location IDs for the orders to query. All locations must belong to the same merchant.  Min: 1 location IDs.  Max: 10 location IDs.
      * @var string[]
      */
    protected $location_ids;
    /**
      * $cursor A pagination cursor returned by a previous call to this endpoint. Provide this to retrieve the next set of results for your original query. See [Pagination](/basics/api101/pagination) for more information.
      * @var string
      */
    protected $cursor;
    /**
      * $query Query conditions used to filter or sort the results. Note that when fetching additional pages using a cursor, the query must be equal to the query used to fetch the first page of results.
      * @var \SquareConnect\Model\SearchOrdersQuery
      */
    protected $query;
    /**
      * $limit Maximum number of results to be returned in a single page. It is possible to receive fewer results than the specified limit on a given page.  Default: `500`
      * @var int
      */
    protected $limit;
    /**
      * $return_entries Boolean that controls the format of the search results. If `true`, SearchOrders will return [`OrderEntry`](#type-orderentry) objects. If `false`, SearchOrders will return complete Order objects.  Default: `false`.
      * @var bool
      */
    protected $return_entries;

    /**
     * Constructor
     * @param mixed[] $data Associated array of property value initializing the model
     */
    public function __construct(array $data = null)
    {
        if ($data != null) {
            if (isset($data["location_ids"])) {
              $this->location_ids = $data["location_ids"];
            } else {
              $this->location_ids = null;
            }
            if (isset($data["cursor"])) {
              $this->cursor = $data["cursor"];
            } else {
              $this->cursor = null;
            }
            if (isset($data["query"])) {
              $this->query = $data["query"];
            } else {
              $this->query = null;
            }
            if (isset($data["limit"])) {
              $this->limit = $data["limit"];
            } else {
              $this->limit = null;
            }
            if (isset($data["return_entries"])) {
              $this->return_entries = $data["return_entries"];
            } else {
              $this->return_entries = null;
            }
        }
    }
    /**
     * Gets location_ids
     * @return string[]
     */
    public function getLocationIds()
    {
        return $this->location_ids;
    }
  
    /**
     * Sets location_ids
     * @param string[] $location_ids The location IDs for the orders to query. All locations must belong to the same merchant.  Min: 1 location IDs.  Max: 10 location IDs.
     * @return $this
     */
    public function setLocationIds($location_ids)
    {
        $this->location_ids = $location_ids;
        return $this;
    }
    /**
     * Gets cursor
     * @return string
     */
    public function getCursor()
    {
        return $this->cursor;
    }
  
    /**
     * Sets cursor
     * @param string $cursor A pagination cursor returned by a previous call to this endpoint. Provide this to retrieve the next set of results for your original query. See [Pagination](/basics/api101/pagination) for more information.
     * @return $this
     */
    public function setCursor($cursor)
    {
        $this->cursor = $cursor;
        return $this;
    }
    /**
     * Gets query
     * @return \SquareConnect\Model\SearchOrdersQuery
     */
    public function getQuery()
    {
        return $this->query;
    }
  
    /**
     * Sets query
     * @param \SquareConnect\Model\SearchOrdersQuery $query Query conditions used to filter or sort the results. Note that when fetching additional pages using a cursor, the query must be equal to the query used to fetch the first page of results.
     * @return $this
     */
    public function setQuery($query)
    {
        $this->query = $query;
        return $this;
    }
    /**
     * Gets limit
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }
  
    /**
     * Sets limit
     * @param int $limit Maximum number of results to be returned in a single page. It is possible to receive fewer results than the specified limit on a given page.  Default: `500`
     * @return $this
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;
        return $this;
    }
    /**
     * Gets return_entries
     * @return bool
     */
    public function getReturnEntries()
    {
        return $this->return_entries;
    }
  
    /**
     * Sets return_entries
     * @param bool $return_entries Boolean that controls the format of the search results. If `true`, SearchOrders will return [`OrderEntry`](#type-orderentry) objects. If `false`, SearchOrders will return complete Order objects.  Default: `false`.
     * @return $this
     */
    public function setReturnEntries($return_entries)
    {
        $this->return_entries = $return_entries;
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
