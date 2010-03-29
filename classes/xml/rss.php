<?php defined('SYSPATH') or die(Kohana::lang('security.noscript'));
/**
 *	Document   : rss.php
 *	Created on : 1 mai 2009, 13:03:03
 *	@author Cedric de Saint Leger <c.desaintleger@gmail.com>
 *
 *	Description:
 *      XML_Rss driver
 */

class XML_Rss extends XML
{
	protected $root_node = 'feed';
								
	protected $root_attributes = array	(
									'version'	=> '2.0',
									);

	public $filters = array(
							'link'			=> 'normalize_uri',
							'docs'			=> 'normalize_uri',
							'guid'			=> 'normalize_uri',
							'pubDate'		=> 'normalize_date',
							'lastBuildDate'	=> 'normalize_date',
							);

	public $headers = array('Content-Type' => 'atom+xml');


	public function normalize_date($value)
	{
		if ( ! is_numeric($value))
		{
			$value = strtotime($value);
		}

		// Convert timestamps to RFC 3339 formatted dates
		return date(DATE_RFC822, $value);
	}
}