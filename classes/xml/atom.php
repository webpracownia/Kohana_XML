<?php defined('SYSPATH') or die(Kohana::lang('security.noscript'));
/**
 *	Document   : xml_atom.php
 *	Created on : 1 mai 2009, 13:03:03
 *	@author Cedric de Saint Leger <c.desaintleger@gmail.com>
 *
 *	Description:
 *      XML_Atom driver
 */

class XML_Atom extends XML
{
	public $root_node = 'feed';
	
	public $namespaces = array	(
								"default"	=> "http://www.w3.org/2005/Atom",
								"osearch"	=> "http://a9.com/-/spec/opensearch/1.1"
								);

	public $filters = array(
							'href'		=> 'normalize_uri',
							'logo'		=> 'normalize_uri',
							'icon'		=> 'normalize_uri',
							'id'		=> 'normalize_uri',
							'updated'	=> 'normalize_date',
							'published'	=> 'normalize_date',
							);

	public $headers = array('Content-Type' => 'application/atom+xml');


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