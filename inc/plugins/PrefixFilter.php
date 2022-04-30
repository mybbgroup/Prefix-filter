<?php
/*
*
* Prefix Filter Plugin
* Copyright 2020 Mostafa Shiraali
* Optimised the code performance, added new features and fixed bugs by MyBB Group team (2021-2022)
*
*/

if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Plugin info
function PrefixFilter_info()
{
	global $lang;
	$lang->load("PrefixFilter");
	return [
		"name"          => $lang->prefixfilter_plugin_title,
		"description"   => $lang->prefixfilter_plugin_description,
		"website"       => "",
		"author"        => "Mostafa Shiraali, optimised and bug-fixed by the MyBB Group team",
		"authorsite"    => "https://github.com/mybbgroup/Prefix-filter",
		"version"       => "1.3",
		"codename"      => "",
		"guid"          => "",
		"compatibility" => "18*"
	];
}

// Plugin install
function PrefixFilter_install()
{
	global $mybb, $db, $config, $lang;
	$lang->load("PrefixFilter");

	// ACP plugin's settings group
	$settings_group = [
		"name"        => "PrefixFilter",
		"title"       => $lang->prefixfilter_settinggroup_title,
		"description" => $lang->prefixfilter_settinggroup_description,
		"disporder"   => "901",
		"isdefault"   => "0"
	];

	$db->insert_query("settinggroups", $settings_group);
	$gid = $db->insert_id();
	
	// Plugin settings
	// Plugin active/inactive state
	$setting[] = [
		"name"        => "PrefixFilter_enable",
		"title"       => $lang->prefixfilter_enable_title,
		"description" => $lang->prefixfilter_enable_description,
		"optionscode" => "yesno",
		"value"       => "1", // default "Enabled"
		"disporder"   => 1,
		"gid"         => intval($gid)
	];

	// Available for usergroups
	$setting[] = [
		"name"        => "PrefixFilter_usergroups",
		"title"       => $lang->prefixfilter_usergroups_title,
		"description" => $lang->prefixfilter_usergroups_description,
		"optionscode" => "groupselect",
		"value"       => "-1", // default "Enabled for all usergroups"
		"disporder"   => 2,
		"gid"         => intval($gid)
	];

	// Enabled in forums
	$setting[] = [
		"name"        => "PrefixFilter_forums",
		"title"       => $lang->prefixfilter_forums_title,
		"description" => $lang->prefixfilter_forums_description,
		"optionscode" => "forumselect",
		"value"       => "-1", // default "Enabled in all forums"
		"disporder"   => 3,
		"gid"         => intval($gid)
	];

	foreach ($setting as $i)
	{
		$db->insert_query("settings", $i);
	}

	rebuild_settings();

	// Create stylesheet
	$stylesheet = @file_get_contents(MYBB_ROOT.'inc/plugins/PrefixFilter/PrefixFilter.css');
	$attachedto = 'forumdisplay.php';
	$name = 'PrefixFilter.css';
	$tid = 1;
	$thisStyleSheet = array(
		'name'         => $name,
		'tid'          => $tid,
		'attachedto'   => $db->escape_string($attachedto),
		'stylesheet'   => $db->escape_string($stylesheet),
		'cachefile'    => $name,
		'lastmodified' => TIME_NOW,
	);
	$query = $db->simple_select('themestylesheets', 'sid', "tid='{$tid}' AND name='{$name}'");
	$sid = (int) $db->fetch_field($query, 'sid');
	if ($sid) {
		$db->update_query('themestylesheets', $thisStyleSheet, "sid='{$sid}'");
	}
	else 
	{
		$sid = $db->insert_query('themestylesheets', $thisStyleSheet);
		$thisStyleSheet['sid'] = (int) $sid;
	}
	require_once MYBB_ADMIN_DIR.'inc/functions_themes.php';
	if (!cache_stylesheet($tid, $name, $stylesheet))
	{
		$db->update_query("themestylesheets", array('cachefile' => "css.php?stylesheet={$sid}"), "sid='{$sid}'", 1);
	}
        update_theme_stylesheet_list($tid, false, true);

	// Create plugin's template group
	$templateset = array(
		"prefix" => "PrefixFilter",
		"title" => "PrefixFilter",
	);
	$db->insert_query("templategroups", $templateset);
	
	// Create plugin's templates
	$db->insert_query("templates",  ["title"=> "PrefixFilter_Forumdisplay","template"=>  $db->escape_string('<div class="prefixfilter_forumdisplay">{$lang->prefixfilter_text} {$prefixes} {$resetbutton}</div>'), "sid"=> 1]);

	$db->insert_query("templates",  ["title"=> "PrefixFilter_Prefix","template"=>  $db->escape_string('<a title="{$p[\'prefix\']}" class="prefixfilter_prefix" href="{$mybb->settings[\'bburl\']}/forumdisplay.php?fid={$fid}&prefix={$p[\'pid\']}">{$p[\'displaystyle\']} ({$p[\'counter\']})</a>'), "sid"=> 1]);
	
	$db->insert_query("templates",  ["title"=> "PrefixFilter_Resetbutton","template"=>  $db->escape_string('<a class="prefixfilter_display_all_button" href="{$mybb->settings[\'bburl\']}/forumdisplay.php?fid={$fid}" title="{$lang->prefixfilter_display_all_button_title}">{$lang->prefixfilter_display_all_button}</a>'),"sid"=> 1]);
}

// Plugin is installed
function PrefixFilter_is_installed()
{
	global $db;

	$query = $db->simple_select('themestylesheets', 'sid', "name='PrefixFilter.css'");
	return ($db->num_rows($query) > 0);
}

// Plugin activate
function PrefixFilter_activate()
{
	// Template changes
	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';
	find_replace_templatesets("forumdisplay_threadlist","#^#i", '{\$prefixfilter}');
}

// Plugin deactivate
function PrefixFilter_deactivate()
{
	// Revert template changes
	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';
	find_replace_templatesets("forumdisplay_threadlist", '#'.preg_quote('{$prefixfilter}').'#i', '',0);
}

// Plugin uninstall
function PrefixFilter_uninstall()
{
	global $mybb, $db, $config, $lang;

	// Delete plugin's setting group + settings
	$db->query("DELETE FROM ".TABLE_PREFIX."settinggroups WHERE name='PrefixFilter'");
	$db->delete_query("settings","name IN ('PrefixFilter_enable','PrefixFilter_usergroups','PrefixFilter_forums')");

	rebuild_settings();
	
	// Delete templates
	$db->delete_query("templates","title IN ('PrefixFilter_Forumdisplay','PrefixFilter_Prefix','PrefixFilter_Resetbutton')");
	
	// Delete template group
	$db->delete_query("templategroups", "prefix in ('PrefixFilter')");	

	// Delete stylesheet
	$where = "name='PrefixFilter.css'";
	// Find the master and any children
	$query = $db->simple_select('themestylesheets', 'tid,name', $where);
	// Delete them all from the server
	while ($styleSheet = $db->fetch_array($query))
	{
		@unlink(MYBB_ROOT."cache/themes/{$styleSheet['tid']}_{$styleSheet['name']}");
		@unlink(MYBB_ROOT."cache/themes/theme{$styleSheet['tid']}/{$styleSheet['name']}");
	}
	// Then delete them from the database
	$db->delete_query('themestylesheets', $where);
	// Now remove them from the CSS file list
	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
	$query = $db->simple_select('themes', 'tid');
	while ($row = $db->fetch_array($query))
	{
		update_theme_stylesheet_list($row['tid']);
	}
}

// Add plugin hooks
$plugins->add_hook('forumdisplay_threadlist', 'PrefixFilter_forumdisplay_threadlist');
$plugins->add_hook('forumdisplay_thread_end', 'PrefixFilter_forumdisplay_thread_end');
$plugins->add_hook('global_start', 'PrefixFilter_global_start');

// Plugin core functions
function PrefixFilter_forumdisplay_thread_end()
{
	global $db, $mybb, $pr, $fid, $tuseronly, $visible_condition, $datecutsql2;

	if($mybb->settings['PrefixFilter_enable'] != 1)
	{
		return;
	}

	if($mybb->settings['PrefixFilter_forums'] == "" || ($mybb->settings['PrefixFilter_forums'] != "-1" && !in_array($fid, explode(",", $mybb->settings['PrefixFilter_forums']))))
	{
		return;
	}

	if(!is_member($mybb->settings['PrefixFilter_usergroups']))
	{
		return;
	}

	static $pr_check = false;

	if(!$pr_check)
	{
		$pr = array();
		// We set $tvisibleonly as a local variable from the global variable $visible_condition
		// in the same way that $tvisibleonly is set from $visible_condition in forumdisplay.php.
		// We don't access $tvisibleonly as a global variable because XThreads modifies it in
		// some scenarios, such that the query below results in an error.
		$tvisibleonly = "AND (t.{$visible_condition})";
		$query2222 = $db->query("
			SELECT          p.pid, p.prefix, p.displaystyle, COUNT(*) AS counter
			FROM            ".TABLE_PREFIX."threads t
			LEFT OUTER JOIN ".TABLE_PREFIX."threadprefixes p
			ON              t.prefix = p.pid
			WHERE           t.prefix !='0' AND t.fid='$fid' $tuseronly $tvisibleonly $datecutsql2
			GROUP BY        p.pid, p.prefix, p.displaystyle
			ORDER BY        p.prefix ASC
		");
		while($row = $db->fetch_array($query2222))
		{
			$pr[] = $row;
		}
		$pr_check = true;
	}
}

// Display thread prefix filter in forumdisplay_threadlist template
function PrefixFilter_forumdisplay_threadlist()
{
	global $lang, $mybb, $fid, $prefixfilter, $pr, $templates;
	$lang->load("PrefixFilter");

	if(!empty($pr))
	{
		$resetbutton = $prefixfilter = $prefixes = '';
		$input_prefix = $mybb->get_input('prefix', MyBB::INPUT_INT);
		$valid_input_prefix = false;
		foreach($pr as $p)
		{
			if($input_prefix == $p['pid'])
			{
				$valid_input_prefix = true;
			}
		}
		foreach($pr as $p)
		{
			if ($input_prefix == $p['pid'] || !$valid_input_prefix) {
				$p['prefix'] = htmlspecialchars_uni($p['prefix']);
				eval("\$prefixes .= \"".$templates->get("PrefixFilter_Prefix")."\";");
			}
		}
		if($valid_input_prefix)
		{
			eval("\$resetbutton = \"".$templates->get("PrefixFilter_Resetbutton")."\";");
		}
		if(!empty($prefixes))
		{
			eval("\$prefixfilter = \"".$templates->get("PrefixFilter_Forumdisplay")."\";");
		}
	}
}

// Templates caching
function PrefixFilter_global_start()
{
	global $templatelist;

	if(in_array(THIS_SCRIPT, explode("," ,"forumdisplay.php")))
	{
		if(isset($templatelist))
		{
			$templatelist .= ",";
		}
		$templatelist .= "PrefixFilter_Forumdisplay,PrefixFilter_Prefix,PrefixFilter_Resetbutton";
	}
}