<?php defined('SYSPATH') or die('No direct script access.');
/**
 *	Document   : atom.php
 *	Created on : 1 mai 2009, 13:03:03
 *	@author Cedric de Saint Leger <c.desaintleger@gmail.com>
 *
 *	Description:
 *      Atom driver
 */

class XML_Driver_Atom extends XML
{
	public $root_node = 'feed';


	protected static function initialize(XML_Meta $meta)
	{
		$meta	->content_type("application/atom+xml")
				->nodes (
							array(
								"feed"				=> array("namespace"	=> "http://www.w3.org/2005/Atom"),
								"entry"				=> array("namespace"	=> "http://www.w3.org/2005/Atom"),
								"author"			=> array("node" => "auth"),
								"href"				=> array("filter"		=> "normalize_uri"),
								"logo"				=> array("filter"		=> "normalize_uri"),
								"icon"				=> array("filter"		=> "normalize_uri"),
								"id"				=> array("filter"		=> "normalize_uri"),
								"updated"			=> array("filter"		=> "normalize_date"),
								"published"			=> array("filter"		=> "normalize_date"),
								"utcOffset"			=> array("filter" 		=> "normalize_datetime"),
								"startDate"			=> array("filter" 		=> "normalize_datetime"),
								'endDate'			=> array("filter" 		=> "normalize_datetime"),
								)
						);
	}
	
	
	public function set_author($user, $fields = array("name", "id"))
	{
		$author = $this->add_node("author");
		$author->from_array($user->as_array($fields));
		return $this;
	}
	
	
	public function set_title($title)
	{
		$this->add_node("title", $title);
		return $this;
	}
	
	
	public function set_updated()
	{
		$this->add_node("updated", time());
		return $this;
	}


	public function normalize_date($value)
	{
		if ( ! is_numeric($value))
		{
			$value = strtotime($value);
		}

		// Convert timestamps to RFC 3339 formatted dates
		return date(DATE_RFC3339, $value);
	}
}