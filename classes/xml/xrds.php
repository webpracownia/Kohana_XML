<?php defined('SYSPATH') or die(Kohana::lang('security.noscript'));
/**
 *	Document   : xrds.php
 *	Created on : 1 mai 2009, 13:03:03
 *	@author Cedric de Saint Leger <c.desaintleger@gmail.com>
 *
 *	Description:
 *      XML_XRDS driver. For Service Discovery.
 */

 class XML_Xrds extends XML
 {
	public $filters = array(
							'LocalID'			=> 'normalize_uri',
							'openid:Delegate'	=> 'normalize_uri',
							'URI'				=> 'normalize_uri'
							);
	
	public $headers = array('Content-Type' => 'application/xrds+xml');
	
	public $root_node = 'xrds:XRDS';
	
	public $namespaces = array	(
								'default'	=> 'xri://$xrd*($v*2.0)',
								'xrds'		=> 'xri://$xrds',
								'openid'	=> 'http://openid.net/xmlns/1.0',
								);
								
								
	public function add_service($type, $uri, $priority = NULL)
	{
		if (! is_null($priority))
		{
			$priority = array("priority"	=> $priority);
		}
		else
		{
			$priority = array();
		}
		
		$service_node = $this->add_node("Service", NULL, $priority);

		if (! is_array($type))
		{
			$type = array($type);
		}
		
		foreach ($type as $t)
		{
			$service_node->add_node("Type", $t);
		}
		$service_node->add_node("URI", $uri);
		
		return $service_node;
	}
	
}