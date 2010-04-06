<?php defined('SYSPATH') or die(Kohana::lang('security.noscript'));
/**
 *	Document   : xml.php
 *	Created on : 1 mai 2009, 13:03:03
 *	@author Cedric de Saint Leger <c.desaintleger@gmail.com>
 *
 *	Description:
 *      XML class. Extend this class to make your own XML based driver (Atom, XRDS, GData, RSS, PodCast RSS, or your own brewed XML format)
 */

 class XML
 {	
	public static $driver = NULL;
	
	public static $encoding = 'UTF-8';
	
	public static $xml_version = "1.0";
	
	public $headers = array('Content-Type' => 'text/xml');
	
	public $root_node = NULL;
	
	public $namespaces = array();
	
	public $root_attributes = array();
	
	public $filters = array();
	
	public $dom_doc = NULL;
	
	public $dom_node = NULL;


	/**
	 * This creates an XML object from the specified driver.
	 * Specify the driver name, or if there is no specific driver, the root node name
	 * Element is a mixed type, XML will attempt to decode it and make an XML object from it.
	 * Can be an assoc array (only the first key will be taken into account), a file, a string, or a DOMNode instance.
	 * @param object $driver [optional]
	 * @param object $element [optional]
	 * @param object $root_node [optional]
	 * @return XML XML object
	 */
	public static function factory($driver = NULL, $element = NULL)
	{
		// Let's attempt to generate a new instance of the subclass corresponding to the driver provided
		$class = 'XML_'.ucfirst($driver);

		if (class_exists($class))
		{
			// driver exists and is valid so it can specified
			self::$driver = $driver;
			return new $class($element);
		}
		else
		{
			// driver does not existso we consider $driver to be the root node of a basic XML document
			return new XML($element, $driver);
		}
	}
	
	
	/**
	 * Class constructor. You should use the factory instead.
	 * @param string $element [optional] What to construct from. Could be some xml string, a file name, or a DOMNode
	 * @param string $root_node [optional] The root node name. To be specified if no driver are used.
	 * @return XML XML object instance
	 */
	public function __construct($element = NULL, $root_node = NULL)
	{
		// Create the initial DOMDocument
		$this->dom_doc = new DOMDocument(self::$xml_version, self::$encoding);
		
		if ($root_node)
		{
			// if a root node is specified, overwrite the current_one
			$this->root_node = $root_node;
		}
			
		if (is_string($element))
		{
			if (is_file($element) OR validate::url($element))
			{
				// Generate XML from a file
				$this->dom_doc->load($element);
			}
			else
			{
				// Generate XML from a string
				$this->dom_doc->loadXML($element);
			}
			// Node is the root node of the document, containing the whole tree
			$this->dom_node = $this->dom_doc->documentElement;
		}
		elseif ($element instanceof DOMNode)
		{
			// This is called from another XML instance ( through add_node, or else...)
			// Let's add that node to the new object node 
			$this->dom_node = $element;
			// And overwrite the document with that node's owner document
			$this->dom_doc = $this->dom_node->ownerDocument;
		}
		elseif ( ! is_null($this->root_node))
		{
			// Create the Root Element from the driver attributes
			$array_ns = explode(":", $this->root_node);
			if (count($array_ns) > 1 AND isset ($this->namespaces[$array_ns[0]]))
			{
				// Create the root node in its prefixed namespace
				$root_node = $this->dom_doc->createElementNS($this->namespaces[$array_ns[0]], $this->root_node);
			}
			else
			{
				// Create the root node
				$root_node = $this->dom_doc->createElement($this->root_node);
			}
			
			// Append the root node to the object DOMDocument, and set the resulting DOMNode as it's node
			$this->dom_node = $this->dom_doc->appendChild($root_node);
			
			// Set the default namespace
			if (isset($this->namespaces["default"]))
			{
				$this->dom_node->setAttribute("xmlns", $this->namespaces["default"]);
			}
			
			// Add other attributes
			$this->add_attributes($this->dom_node, $this->root_attributes);

		}
		else
		{
			throw Kohana_Exception("You have to specify a root_node, either in your driver or in the constructor if you're not using any.");
		}
	}
	
	
	/**
	 * Adds a node to the document
	 * @param string $name Name of the node. Prefixed namespaces are handled automatically.
	 * @param value $value [optional] value of the node (will be filtered). If value is not valid CDATA, 
	 * it will be wrapped into a CDATA section
	 * @param array $attributes [optional] array of attributes. Prefixed namespaces are handled automatically.
	 * @return XML instance for the node that's been added.
	 */
	public function add_node($name, $value = NULL, $attributes = array())
	{	
		// Trim elements
		$name = trim($name);
		$value = trim($value);
		
		// Create the element
		$node = $this->create_element($name);

		// Add the attributes
		$this->add_attributes($node, $attributes);
		
		// Add the value if provided
		if ($value !== NULL)
		{			
			$value = strval($this->apply_filter($name, $value));
			
			if (str_replace(array('<', '>', '&'), "", $value) === $value)
			{
				// Value is valid CDATA, let's add it as a new text node
				$value = $this->dom_doc->createTextNode($value);
			}
			else
			{
				// We shall create a CDATA section to wrap the text provided
				$value = $this->dom_doc->createCDATASection($value);
			}
			$node->appendChild($value);
		}

		// return an instance of this class with the child node
		return XML::factory(self::$driver, $this->dom_node->appendChild($node));
	}
	
	
	/**
	 * Magic get returns the first child node matching the value
	 * @param object $value
	 * @return 
	 */
	public function __get($value)
	{
		if ( ! isset($this->$value))
		{
			return reset($this->get($value));
		}
		parent::__get($value);
	}
	
	
	/**
	 * Gets all nodes matching a name and returns them as an array.
	 * Can also be used to get a pointer to a particular node and then deal with that node as an XML instance.
	 * @param string $value name of the nodes desired
	 * @param bool $as_array [optional] whether or not the nodes should be returned as an array
	 * @param string $namespace [optional] specify a namespace (prefix or address) if you want to confine your search to a specific NS
	 * @return array Multi-dimensional array or array of XML instances
	 */
	public function get($value, $as_array = TRUE, $namespace = "*")
	{
		$return = array();
		
		if ( $namespace !== "*" AND ! stristr($namespace, "://"))
		{
			$namespace = $this->namespaces[$namespace];
		}

		foreach ($this->dom_doc->getElementsByTagNameNS($namespace, $value) as $item)
		{
			if ($as_array)
			{
				$array = $this->_as_array($item);
				foreach ($array as $val)
				{
					$return[] = $val;
				}
			}
			else
			{
				$return[] = XML::factory(NULL, $item);
			}
		}
		return $return;
	}



	/**
	 * Queries the document with an XPath query
	 * @param string $query XPath query
	 * @param bool $as_array [optional] whether or not the nodes should be returned as an array
	 * @return array Multi-dimensional array or array of XML instances
	 */
	public function xpath($query, $as_array = TRUE)
	{
		$return = array();
		
		$xpath = new DOMXPath($this->dom_doc);
		
		foreach ($xpath->query($query) as $item)
		{
			if ($as_array)
			{
				$array = $this->_as_array($item);
				foreach ($array as $val)
				{
					$return[] = $val;
				}
			}
			else
			{
				$return[] = XML::factory(NULL, $item);
			}
		}
		return $return;
	}

	
	/**
	 * Exports the document as a multi-dimensional array.
	 * Handles element with the same name.
	 * 
	 * Root node is ignored, as it is known and available in the driver.
	 * Example : 
	 * <node_name attr_name="val">
	 * 		<child_node_name>
	 * 			value1
	 * 		</child_node_name>
	 * 		<child_node_name>
	 * 			value2
	 * 		</child_node_name>
	 * </node_name>
	 * 
	 * Here's the resulting array structure :
	 * array ("node_name" => array(
	 * 					// array of nodes called "node_name"	
	 * 					0 => array(
	 *							// Attributes of that node
	 *							"xml_attributes" => array(
	 *											"attr_name" => "val",
	 *													)
	 *							// node contents
	 * 							"child_node_name" => array( 
	 * 												// array of nodes called "child_node_name"
	 * 												0 => value1,
	 * 												1 => value2,
	 * 														)
	 * The output is retro-actively convertible to XML using from_array().
	 * @return array
	 */
	public function as_array()
	{
		$dom_element = $this->dom_node;
		
		$return = array();
		
		// This function is run on a whole XML document and this is the root node.
		// That root node shall be ignored in the array as it driven by the driver and handles document namespaces.
		foreach($dom_element->childNodes as $dom_child)
		{
			if ($dom_child->nodeType == XML_ELEMENT_NODE)
			{
				// Let's run through the child nodes
				$child = $this->_as_array($dom_child);
				//XML::factory(self::$driver, $dom_child)->_as_array();
				
				foreach ($child as $key=>$val)
				{
					$object_element[$key][]=$val;
				}
			}
		}
		
		return $return;
	}



	/**
	 * Recursive as_array for child nodes
	 * @param DOMNode $dom_node
	 * @return Array
	 */
	private function _as_array(DOMNode $dom_node)
	{
		// All other nodes shall be parsed normally : attributes then text value and child nodes, running through the XML tree
		$object_element = array();
			
		// Get attributes
		if ($dom_node->hasAttributes())
		{
	 		$object_element[$dom_node->nodeName]['xml_attributes'] = array();
			foreach($dom_node->attributes as $attName => $dom_attribute)
			{
				$object_element[$dom_node->nodeName]['xml_attributes'][$attName] = $dom_attribute->value;
			}
		}
	
		// Get children, run through XML tree
		if ($dom_node->hasChildNodes())
		{
			if (!$dom_node->firstChild->hasChildNodes())
			{
				// Get text value
				$object_element[$dom_node->nodeName] = trim($dom_node->firstChild->nodeValue);
			}
	
			foreach($dom_node->childNodes as $dom_child)
			{
				if ($dom_child->nodeType == XML_ELEMENT_NODE)
				{
					$child = $this->_as_array($dom_child);
					//XML::factory(self::$driver, $dom_child)->as_array();
						
					foreach ($child as $key=>$val)
					{
						$object_element[$dom_node->nodeName][$key][]=$val;
					}
				}
			}
		}
		return $object_element;
	}


	
	/**
	 * Converts an array to XML. Expected structure is given in as_array().
	 * However, from_array() is essentially more flexible regarding to the input array structure,
	 * as we don't have to bother about nodes having the same name.
	 * Try something logical, that should work as expected.
	 * @param object $mixed
	 * @return XML
	 */
	public function from_array($array)
	{
		$this->_from_array($array, $this->dom_node);

		return $this;
	}



	/**
	 * Array shall be like : array('element_name' => array( 0 => text, 'xml_attributes' => array()));
	 * @param object     $mixed
	 * @param DOMElement $dom_element
	 * @return 
	 */
	protected function _from_array($mixed, DOMElement $dom_element)
	{
		if (is_array($mixed))
		{
			foreach( $mixed as $index => $mixed_element )
			{
				if ( is_numeric($index) )
				{
					// If we have numeric keys, we're having multiple children of the same node.
					// Append the new node to the current node's parent
					// If this is the first node to add, $node = $dom_element
					$node = $dom_element;
					if ( $index != 0 )
					{
						// If not, lets create a copy of the node with the same name 
						$node = $this->create_element($dom_element->tagName);
						// And append it to the parent node
						$node = $dom_element->parentNode->appendChild($node);
					}
					$this->_from_array($mixed_element, $node);
				}
				elseif ($index == "xml_attributes")
				{
					// Add attributes to the node
					$this->add_attributes($dom_element, $mixed_element);
				}
				else
				{
					// Create a new element with the key as the element name.
					// Create the element corresponding to the key
					$node = $this->create_element($index);
					// Append it
					$dom_element->appendChild($node);
					
					// Treat the array by recursion
					$this->_from_array($mixed_element, $node);
				}
			}
		}
		else
		{
			// This is a string value that shall be appended as such
			$mixed = $this->apply_filter($dom_element->tagName, $mixed);
			$dom_element->appendChild($this->dom_doc->createTextNode($mixed));
		}
	}



	/**
	 * Outputs nicely formatted XML when converting as string
	 * @return string
	 */
	public function __toString()
	{
		return $this->render(TRUE);
	}



	/**
	 * Render the XML.
	 * @param boolean $formatted [optional] Should the output be nicely formatted and indented ?
	 * @return string
	 */
	public function render($formatted = FALSE)
	{
		$this->dom_doc->formatOutput = $formatted;
		return $this->dom_doc->saveXML();
	}


	/**
	 * Outputs the XML in a file
	 * @param string filename
	 * @return 
	 */
	public function save($file)
	{
		return $this->dom_doc->save($file);
	}


	/**
	 * Applies filter on a value.
	 * These filters are callbacks usually defined in the driver.
	 * They allow to format dates, links, standard stuff, and play 
	 * as you wish with the value before it is added to the document.
	 * 
	 * You could even extend it and modidy the node name.
	 * 
	 * @param string $name
	 * @param string $value
	 * @return string $value formatted value
	 */
	protected function apply_filter($name, $value)
	{
		if (array_key_exists($name, $this->filters))
		{
			$function = $this->filters[$name];
			return call_user_func(array($this, $function), $value);
		}
		return $value;
	}



	/**
	 * This is a classic filter that takes a uri and makes a proper link
	 * @param object $value
	 * @return $value
	 */
	public function normalize_uri($value)
	{
		if (strpos($value, '://') === FALSE)
		{
			// Convert URIs to URLs
			$value = URL::site($value, 'http');
		}
		return $value;
	}


	/**
	 * Creates an element, sorts out namespaces (default / prefixed)
	 * @param string $name element name
	 * @return DOMElement
	 */
	private function create_element($name)
	{
		// Let's check if the element name has a namespace prefix, and if this prefix is defined in our driver
		$ns_array = explode(':', $name);
		if (count($ns_array) > 1 AND isset($this->namespaces[$ns_array[0]]))
		{
			list ($prefix) = $ns_array;
			// Register the prefixed namespace
			$this->dom_doc->documentElement->setAttributeNS("http://www.w3.org/2000/xmlns/" ,"xmlns:$prefix", $this->namespaces[$prefix]);

			// Create the prefixed element within that namespace
			$node = $this->dom_doc->createElementNS($this->namespaces[$prefix], $name);

			// Namespace is registered, we can get rid of it.
			unset($this->namespaces[$prefix]);
		}
		else
		{
			// Simply create the element
			$node = $this->dom_doc->createElement($name);
		}
		return $node;
	}


	/**
	 * Applies attributes to a node
	 * @param DOMNode $node
	 * @param array  $attributes as key => value
	 * @return DOMNode
	 */
	private function add_attributes(DOMNode $node, $attributes)
	{
		foreach ($attributes as $key => $val)
		{
			// Trim elements
			$key = trim($key);
			$val = trim($val);
			
			// Set the attribute
			// Let's check if the attribute name has a namespace prefix, and if this prefix is defined in our driver
			$ns_array = explode(':', $key);
			if (count($ns_array) > 1 AND isset($this->namespaces[$ns_array[0]]))
			{
				list ($prefix) = $ns_array;
				// Register the prefixed namespace
				$this->dom_node->setAttributeNS("http://www.w3.org/2000/xmlns/" ,"xmlns:$prefix", $this->namespaces[$prefix]);
				// Add the prefixed attribute within that namespace
				$node->setAttributeNS($this->namespaces[$prefix], $key, $val);
				// Namespace is registered, we can get rid of it.
				unset($this->namespaces[$prefix]);
			}
			else
			{
				// Simply add the attribute
				$node->setAttribute($key, $val);
			}
		}
		return $node;
	}
}
?>