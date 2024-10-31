<?php
/*
	Plugin Name: Related Pages
	Plugin URI: http://www.janhvizdak.com/related-pages-wordpress-plugin.html
	Description: A plugin that allows one to list related pages inlcuding any web document under any post or page.
	Version: 1.1.9.0
	Author: Jan Hvizdak
	Author URI: http://www.janhvizdak.com/
*/


/*  Copyright 2013  Jan Hvizdak  (email : postmaster@aqua-fish.net)

	This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

	You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301,  USA
*/

	//co je globalna premenna sluziaca pre rozne funkcie
	global $wpdb;

	define('WP_POST_REVISIONS', false);			//ak toto nie je zadefinovane, potom to savne 2x
	define('PRI_RPD_DEF'      , 10);			//priorita pluginu

	//definujeme si premenne, ktore budeme pouzivat v celom plugine
	$tabulka_linky = $wpdb->prefix . "rpd_related_pages";
	$tabulka_rels  = $wpdb->prefix . "rpd_relations";
	$tabulka_names = $wpdb->prefix . "rpd_titles";			//tabulka s nazvami, ak nechceme mat "Related pages"
	$tabulka_setts = $wpdb->prefix . "rpd_settings";		//tabulka s nastaveniami

	//zadefinujeme si defaultny pokec nad linkami
	define('DEF_RPD_TEXT_TMP', rpd_get_setting("header"));
	$def_tmp = DEF_RPD_TEXT_TMP;
	if(empty($def_tmp))
		define('DEF_RPD_TEXT', 'Related pages');
			else
				define('DEF_RPD_TEXT', DEF_RPD_TEXT_TMP);

	$dlzka_text    = 50;						//dlzka vstupu - vo formulari
	$mouseover     = " onmouseover=\"this.style.backgroundColor='#ffff66';\" onmouseout=\"this.style.backgroundColor='#d4e3e5';\"";		//klasicky mouseover v tabulke

	//zavolame funkciu na zobrazenie obrazovky
	if(function_exists('add_action'))
		{
			add_action('admin_menu', 'rel_pages_menu');

			//ukladanie dat, ktore sa poslu cez plugin
			add_action( 'save_post', 'relatedpages_save_postdata' );
			//if (current_user_can('delete_posts'))
				add_action('delete_post', 'relatedpages_delete_postdata', PRI_RPD_DEF);

			//pridame box do editacie clankov, stranok - hlavny engine
			add_action( 'add_meta_boxes', 'relatedpages_add_custom_box' );
		}

	//pridam odkaz do nastrojov v ramci wp admin panelu
	function rel_pages_menu()
		{
			add_management_page('Related Pages Plug-In Management', 'Related Pages', 8, 'relatedpages', 'related_pages');
		}

	//funkcia na defaultne vypnutie zobrazovania niektorych okien... napr bakup/restore pri prvom zobrazeni
	function rpd_def_hide($in)
		{
			$cookie = "style".$in;
			if(!isset($_COOKIE[$cookie]))
				setcookie($cookie, "none", time()+3600*24*30);

			return "";
		}

	//funkcia na osetrenie vstupu do sql
	function rpd_sql_prepare($in)
		{
			$breakme = 400;			//pre pripad, ze sa nieco totalne poserie
			$counter = 0;

			//htmlspecialchars_decode
			while(htmlspecialchars_decode($in)!=$in)
				{
					$counter++;
					$in = htmlspecialchars_decode($in);
					if($counter>=$breakme)
						break;		//ak je nieco seriozne posrate
				}

			//stripslashes
			while(stripslashes($in)!=$in)
				{
					$counter++;
					$in = stripslashes($in);
					if($counter>=$breakme)
						break;		//ak je nieco seriozne posrate
				}

			$in = htmlspecialchars(addslashes($in));
			return $in;				//je to ok na pouzitie v sql
		}

	//funkcia na zobrazenie spravneho stringu po citani z databazy
	function rpd_sql_nice($in)
		{
			$breakme = 400;			//pre pripad, ze sa nieco totalne poserie
			$counter = 0;

			//htmlspecialchars_decode
			while(htmlspecialchars_decode($in)!=$in)
				{
					$counter++;
					$in = htmlspecialchars_decode($in);
					if($counter>=$breakme)
						break;		//ak je nieco seriozne posrate
				}

			//stripslashes
			while(stripslashes($in)!=$in)
				{
					$counter++;
					$in = stripslashes($in);
					if($counter>=$breakme)
						break;		//ak je nieco seriozne posrate
				}

			return $in;				//je to ok na pouzitie v sql
		}

	//styly css
	function rpd_stylesheet()
		{
			//vlozime css
			wp_register_style( 'prefix-style', plugins_url('style.css', __FILE__) );
			wp_enqueue_style( 'prefix-style' );

			//vlozime javascript
			wp_enqueue_script(
				'newscript',
				plugins_url('scripts.js', __FILE__),
				array('scriptaculous')
					);
		}

	//css pre beznych ludi
	function rpd_scripts_css()
		{
			wp_register_style( 'prefix-style', plugins_url('style_rpd.css', __FILE__) );
			wp_enqueue_style( 'prefix-style' );
		}

	//funkcia na vratenie url v ramci control panelu
	function tato_url()
		{
			$url = $_SERVER['REQUEST_URI'];

			$url = str_replace("&zmaz_id=".$_REQUEST['zmaz_id'],"",$url);

			return $url;
		}

	//funkcia na ziskanie nastavenia
	function rpd_get_setting($in)
		{
			global $wpdb, $tabulka_setts;

			$sql      = "SELECT hodnota FROM ".$tabulka_setts." WHERE setting_nazov LIKE '".rpd_sql_prepare($in)."';";
			$vysledok = $wpdb->get_var($sql);

			//zadefinujeme si defaultne hodnoty
			if(empty($vysledok))
				switch ($in) {
					case "anchor_length":
						$vysledok = 0;
						break;
					case "col_count":
						$vysledok = 1;
						break;
					default:
						$vysledok = $vysledok;
						break;
					}

			return $vysledok;
		} 

	//funkcia na optimalizaciu tabulky po modifikacii/mazani
	function rpd_opt_tables($tabulka)
		{
			global $wpdb;

			$sql = "OPTIMIZE TABLE ".rpd_sql_prepare($tabulka).";";
			$wpdb->query($sql);

			return true;
		}

	//samotna funkcia na zobrazenie v admin panele
	function related_pages()
		{
			global $wpdb, $dlzka_text, $tabulka_linky, $tabulka_setts, $tabulka_rels, $mouseover;

			//co ak chceme nieco zmazat?
			$zmazma = intval($_REQUEST['zmaz_id']);
			if($zmazma>0)
				{
					$sql = "DELETE FROM ".$tabulka_linky." WHERE id = ".$zmazma." LIMIT 1;";
					$wpdb->query($sql);
					rpd_opt_tables($tabulka_linky);
				}

			//modifikovane url?
			$mod_url = $_POST['mod_url'];
			$mod_men = $_POST['mod_name'];
			$mod_css = $_POST['mod_trieda'];
			$mod_atr = $_POST['mod_attr'];
			$mod_cil = $_POST['mod_ciel'];
			$mod_id  = intval($_POST['mod_id']);

			$exec_result = "";

			if( ($mod_url!='') && ($mod_id>0) )
				{
					$sql = "UPDATE ".$tabulka_linky." SET url = '".rpd_sql_prepare($mod_url)."' , text_odkazu = '".rpd_sql_prepare($mod_men)."' , atribut = '".rpd_sql_prepare($mod_atr)."' , ciel = '".rpd_sql_prepare($mod_cil)."' , trieda = '".rpd_sql_prepare($mod_css)."' WHERE id = '".rpd_sql_prepare($mod_id)."' LIMIT 1;";
					$wpdb->query($sql);

					$exec_result = "Record has been altered!";
				}

			//nove udaje, ak je formular submitnuty
			$nova_url = $_POST['rpd_url'];
			$nova_men = $_POST['rpd_name'];
			$nova_atr = $_POST['rpd_attr'];
			$nova_cil = $_POST['rpd_trgt'];
			$nova_css = $_POST['rpd_class'];

			//ak bol naozaj formular submitnuty, pridame do databazy
			if($nova_url!='')
				{
					$sql = "INSERT INTO ".$tabulka_linky." VALUES ( 'NULL' , '".rpd_sql_prepare($nova_url)."' , '".rpd_sql_prepare($nova_men)."' , '".rpd_sql_prepare($nova_atr)."' , '".rpd_sql_prepare($nova_cil)."' , '".rpd_sql_prepare($nova_css)."' );";
					//echo $sql;
					$wpdb->query($sql);
					$exec_result = "Record has been added!";
				}

			//co ak menime titul
			$novy_titul   = $_POST['def_titul'];
			if(!empty($novy_titul))
				{
					$sql = "REPLACE INTO ".$tabulka_setts." VALUES ( 'header' , '".rpd_sql_prepare($novy_titul)."' );";
					$wpdb->query($sql);
				}

			$header_titul = rpd_sql_nice(rpd_get_setting("header"));		//nacitame si header

			define('BOX_CHECKED_CL1', 'checked=\'checked\'');
			define('BOX_CHECKED_CL2', '');

			if(empty($header_titul))
				$header_titul = DEF_RPD_TEXT;
			//hlavna obrazovka v nastaveniach
			echo "<div id=\"main_rpd\">
<h2>Configuration screen of Related Pages</h2>
";

			echo "<p>This is a basic version, it is strongly recommended to upgrade to PRO version: Find out more at <a href=\"http://www.janhvizdak.com/related-pages-wordpress-plugin.html\" target=\"_blank\">janhvizdak.com/related-pages-wordpress-plugin.html</a></p>";

			echo "<hr class=\"style-one\" />";

			echo "<p>Here below you can add, modify or delete links that are being used within <b>Related Pages</b> feature that is brought to you by this plugin. A short overview anyway...</p>
<ol>
	<li>URL's and anchor text's can be duplicate. This is allowed due to attributes, it could be useful to use more same looking links.</li>
	<li>URL's don't have to point to documents within your website only, they can point to any document online including, but not limiting to pictures, videos, web pages, PDF documents.</li>
	<li>Once links are defined here, edit or create posts/pages where you'll be able to append links specified below.</li>
</ol>
</div>
<h3 id=\"settings\"><a name=\"settings\">Settings</a> <span onclick=\"showhide('settings_frm')\">&#9650;&#9660;</span></h3>
<form action =\"".$_SERVER['REQUEST_URI']."#settings\" method=\"post\" enctype=\"application/x-www-form-urlencoded\" class=\"hoverform\" id=\"settings_frm\">

	<p><label for=\"def_titul\">By default there's a heading above all related pages once at least one link is associated with some page, and by default this label is \"<b>".$header_titul."</b>\". Of course, this can be modified below.</label></p>
	<p><input type=\"text\" id=\"def_titul\" name=\"def_titul\" value=\"".str_replace("\"","&quot;",$header_titul)."\" size=\"".$dlzka_text."\" required /></p>

	<p><label for=\"def_cols2\">By default all chosen links below content are shown in one column only. However you can let them show in two columns, and moreover you're allowed to specify maximum length of anchor text for a two-column display - if maximum length is exceeded by any anchor text, links will be shown in one column only.</label></p>
	<p><input type=\"radio\" id=\"def_cols1\" name=\"def_cols\" value=\"1\" ".BOX_CHECKED_CL1." checked=\"checked\" required /> 1 column display only</p>
	<p><input type=\"radio\" id=\"def_cols2\" name=\"def_cols\" value=\"2\" ".BOX_CHECKED_CL2." onclick=\"alert('This feature is not available for basic version.'); return false;\" /> 2 column display</p>

	<p><input type=\"submit\" value=\"Confirm all changes!\" /></p>
</form>
";

			echo "<hr class=\"style-one\" />
<h3><a name=\"expimp\">Exports / Imports &amp; Cleanup &amp; Backup / Restore</a> <span onclick=\"alert('This feature is not available for basic version.'); return false;\">&#9650;&#9660;</span></h3>
<div id=\"expimp_div\">
</div>
";

			echo "<hr class=\"style-one\" />
";

			if($exec_result!='')
				echo "<div><p><strong>".$exec_result."</strong></div>
";

			echo "<h3><a name=\"add_mdf\">Add/Delete/Modify records</a> <span onclick=\"showhide('managingfrm')\">&#9650;&#9660;</span></h3>
<form action=\"".str_replace("&","&amp;",$_SERVER['REQUEST_URI'])."#linkmanager\" method=\"post\" enctype=\"application/x-www-form-urlencoded\" class=\"hoverform\" id=\"managingfrm\">
	<p>New URL below (usually starts with <i>http://</i> or <i>https://</i> )</p>
	<p><input type=\"text\" name=\"rpd_url\" id=\"rpd_url\" value=\"http://\" size=\"".$dlzka_text."\" required /></p>
	<p>Anchor text (for example <i>Page devoted to Alfa Romeo 4C at alfaromeo.it</i> )</p>
	<p><input type=\"text\" name=\"rpd_name\" id=\"rpd_name\" value=\"\" size=\"".$dlzka_text."\" required placeholder=\"Anchor text goes here - Required\" /></p>
	<p>Attribute below (can be left empty, you can also use <i>rel='nofollow'</i> or <i>title='Document about 4C'</i> for example... whatever suits your needs</p>
	<p><input type=\"text\" name=\"rpd_attr\" id=\"rpd_attr\" size=\"".$dlzka_text."\" placeholder=\"Attribute - not required\" /></p>
	<p>Target below (can be left as per default setting, otherwise choose one of the options)</p>
	<p><select name=\"rpd_trgt\" id=\"rpd_trgt\">
		<option value=\"default\">default</option>
		<option value=\"_blank\">_blank</option>
		<option value=\"_self\">_self</option>
		<option value=\"_parent\">_parent</option>
		<option value=\"_top\">_top</option>
</select></p>
	<p>CSS class (can be left empty, otherwise specify desired CSS class - this can be found in your CSS file)</p>
	<p><input type=\"text\" name=\"rpd_class\" id=\"rpd_class\" size=\"".$dlzka_text."\" placeholder=\"CSS class for link - not required\" /></p>
	<p><input type=\"submit\" value=\"Add the link above!\" /></p>
</form>
";

			//zobraznie tabulky s linkami
			echo "<hr class=\"style-one\" />
<h3><a name=\"linkmanager\">Existing links</a> <span onclick=\"showhide('linkmanager_frm')\">&#9650;&#9660;</span></h3>
";
			$contents = "<table class=\"hovertable\" id=\"linkmanager_frm\">
	<tr>
		<th class=\"extra_th\">#&nbsp;<span onclick=\"alert('This feature is not available for basic version.'); return false;\" id=\"ord1\">&#9650;</span><span onclick=\"alert('This feature is not available for basic version.'); return false;\" id=\"ord2\">&#9660;</span></th>
		<th class=\"extra_th\">URL&nbsp;<span onclick=\"alert('This feature is not available for basic version.'); return false;\" id=\"ord3\">&#9650;</span><span onclick=\"alert('This feature is not available for basic version.'); return false;\" id=\"ord4\">&#9660;</span></th>
		<th class=\"extra_th\">Anchor tex&nbsp;<span onclick=\"alert('This feature is not available for basic version.'); return false;\" id=\"ord5\">&#9650;</span><span onclick=\"alert('This feature is not available for basic version.'); return false;\" id=\"ord6\">&#9660;</span></th>
		<th class=\"extra_th\">Target&nbsp;<span onclick=\"alert('This feature is not available for basic version.'); return false;\" id=\"ord7\">&#9650;</span><span onclick=\"alert('This feature is not available for basic version.'); return false;\" id=\"ord8\">&#9660;</span></th>
		<th class=\"extra_th\">CSS Class&nbsp;<span onclick=\"alert('This feature is not available for basic version.'); return false;\" id=\"ord9\">&#9650;</span><span onclick=\"alert('This feature is not available for basic version.'); return false;\" id=\"ord10\">&#9660;</span></th>
		<th class=\"extra_th\">Attribute&nbsp;<span onclick=\"alert('This feature is not available for basic version.'); return false;\" id=\"ord11\">&#9650;</span><spanonclick=\"alert('This feature is not available for basic version.'); return false;\" id=\"ord12\">&#9660;</span></th>
		<th class=\"extra_th\">Delete</th>
		<th class=\"extra_th\">Modify</th>
	</tr>
";

			$ordercook = md5(mt_rand(1,999));

			$sql        = "SELECT id, url, text_odkazu, atribut, ciel, trieda FROM ".$tabulka_linky." ORDER BY id ASC;";
			$zaznamy    = $wpdb->get_results($sql);
			$i          = 0;
			$jsaddition = "";
			$paging     = "";
			$stranky    = 0;

			$dalsia_strana= 0;

			$trdisplay    = " style=\"display:table-row\" ";

			if($zaznamy)
				{
					foreach ( $zaznamy as $zaznam ) 
						{
							$i++;
							$contents .= "<tr ".$mouseover." id=\"tbrow_".$i."\"".$trdisplay."><td class=\"extra_th\">".rpd_sql_nice($zaznam->id)."</td><td class=\"extra_th\"><a href=\"".rpd_sql_nice($zaznam->url)."\" target=\"_blank\" id=\"row".$i."_1\">".rpd_sql_nice($zaznam->url)."</a></td><td class=\"extra_th\" id=\"row".$i."_5\">".rpd_sql_nice($zaznam->text_odkazu)."</td><td class=\"extra_th\" id=\"row".$i."_2\">".rpd_sql_nice($zaznam->ciel)."</td><td class=\"extra_th\" id=\"row".$i."_3\">".rpd_sql_nice($zaznam->trieda)."</td><td class=\"extra_th\" id=\"row".$i."_4\">".rpd_sql_nice($zaznam->atribut)."</td><td class=\"extra_th\"><a href=\"".tato_url()."&amp;zmaz_id=".rpd_sql_nice($zaznam->id)."\" title=\"Delete this URL\" onclick=\"return deleteURL('".rpd_sql_nice($zaznam->url)."');\">Delete URL!</a></td><td class=\"extra_th\"><a href=\"#tbrow_".$i."\" onclick=\"modifyURL('tbrow_".$i."_mod','trrow_".$i."_mod','".$i."','".rpd_sql_nice($zaznam->id)."','".rpd_sql_nice($zaznam->ciel)."');\">Click to modify!</a></td></tr><tr id=\"trrow_".$i."_mod\" class=\"inv\"><td class=\"extra_th\" colspan=\"8\" id=\"tbrow_".$i."_mod\"></td></tr>
";
						}

					$jsaddition = "<script type=\"text/javascript\">
<!--
var max_pgs = ".($dalsia_strana+1).";
var max_iO  = ".$i.";

##_JS_ADDITION_REPLACE0_##
-->
</script>";
				}
					else
						{
							$contents .= "<tr><td colspan=\"8\">No existing links yet. Please, add one or more above.</td></tr>";

							$jsaddition = "<script type=\"text/javascript\">
<!--
##_JS_ADDITION_REPLACE0_##
-->
</script>";
						}

			$js_replacement[0] = "//vytvorenie cookie
function createCookie(name, value, days)
	{
		if (days)
			{
				var date = new Date();
				date.setTime(date.getTime() + (days * 24 * 60 * 60 * 365));
				var expires = \"; expires=\" + date.toGMTString();
			}
				else var expires = \"\";
		document.cookie = name + \"=\" + value + expires + \"; path=/\";
	}

//citanie cookie
function getCookie(c_name)
	{
		if (document.cookie.length > 0)
			{
				c_start = document.cookie.indexOf(c_name + \"=\");
				if (c_start != -1)
					{
						c_start = c_start + c_name.length + 1;
						c_end = document.cookie.indexOf(\";\", c_start);
						if (c_end == -1)
							{
								c_end = document.cookie.length;
							}
						return unescape(document.cookie.substring(c_start, c_end));
					}
			}
		return \"\";
	}

//defaultne nastavenie stylov podla cookies
function nastav_styly()
	{
		//moze byt none, alebo block
		var myarray = ['settings_frm','managingfrm','linkmanager_frm','expimp_div','backup','restore','import','export','cleanup'];

		for (var i = 0; i < myarray.length; i++)
			{
				if( (getCookie('style'+myarray[i])=='none') || ( (getCookie('style'+myarray[i])=='') && (i!=1) && (i!=2) ) )
					document.getElementById(myarray[i]).style.display = 'none';
						else
							document.getElementById(myarray[i]).style.display = 'block';
			}
		return true;
	}

nastav_styly();
";

			echo "
".$contents."
</table>
</div>
";

			$jsaddition = str_replace("##_JS_ADDITION_REPLACE0_##",$js_replacement[0],$jsaddition);		//aby sme mali vypis normalny aj pri 0 odkazoch
			echo $jsaddition;

			//echo mt_rand(1,10000);
		}

	//funkcia na vytovorenie tabuliek, ak neexistuju
	function vytvor_tabulky()
		{
			global $wpdb, $tabulka_linky, $tabulka_rels, $tabulka_setts, $tabulka_names;

			$sql = "CREATE TABLE IF NOT EXISTS `".$tabulka_rels."` (
  `postid` int(11) unsigned NOT NULL,
  `wordid` int(11) unsigned NOT NULL,
  KEY `postid` (`postid`,`wordid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

			$wpdb->query($sql);

			$sql = "CREATE TABLE IF NOT EXISTS `".$tabulka_linky."` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `url` varchar(250) COLLATE utf8_unicode_ci NOT NULL,
  `text_odkazu` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `atribut` text COLLATE utf8_unicode_ci NOT NULL,
  `ciel` enum('_blank','_self','_parent','_top','default') COLLATE utf8_unicode_ci NOT NULL,
  `trieda` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ciel` (`ciel`),
  KEY `trieda` (`trieda`),
  KEY `url` (`url`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

			$wpdb->query($sql);

			$sql = "CREATE TABLE IF NOT EXISTS `".$tabulka_names."` (
  `id` int(11) unsigned NOT NULL,
  `force_name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

			$wpdb->query($sql);

			$sql = "CREATE TABLE IF NOT EXISTS `".$tabulka_setts."` (
  `setting_nazov` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `hodnota` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  UNIQUE KEY `setting_nazov` (`setting_nazov`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

			$wpdb->query($sql);

			return true;
		}

	/* samotne pridavanie okienka v ramci pridavania a editacie strnaok */
	function relatedpages_add_custom_box()
		{
			$screens = array( 'post', 'page' );
			foreach ($screens as $screen)
				{
					add_meta_box('relatedpages_sectionid',__( 'Related documents', 'relatedpages_textdomain' ),'relatedpages_inner_custom_box',$screen);
				}
		}

	//funkcia na ziskanie nadpisu nad related pages, ak je teda uzivatelom definovana
	function rpd_ziskaj_nadpis($in)
		{
			global $wpdb, $tabulka_names;

			$sql   = "SELECT force_name FROM ".$tabulka_names." WHERE id = '".rpd_sql_prepare($in)."' LIMIT 1;";
			$titul = rpd_sql_nice($wpdb->get_var($sql));

			if(empty($titul))
				$titul = DEF_RPD_TEXT;

			return $titul;
		}

	/*vypisanie obsahu okienka */
	function relatedpages_inner_custom_box( $post )
		{
			global $wpdb, $tabulka_linky, $tabulka_rels, $dlzka_text;
			// Use nonce for verification
			wp_nonce_field( plugin_basename( __FILE__ ), 'relatedpages_noncename' );

			// The actual fields for data entry
			// Use get_post_meta to retrieve an existing value from the database and use the value for the form
			$value = get_post_meta( $_POST['post_ID'], $key = '_my_meta_value_key', $single = true );

			_e("<p>Select one or more links from the list below, these documents will be shown as <i>".DEF_RPD_TEXT."</i> under page that's edited above. <b>Dynamic search</b> with records being shown upon typing in a box is only available in <a href=\"http://www.janhvizdak.com/related-pages-wordpress-plugin.html\" target=\"_blank\"><b>PRO version</b></a>.</p>" );

			//nacitame si vsetky aktivne odkazy... aby sme ich vedeli spravit ako zaskrtnuty checkbox
			$sql        = "SELECT wordid FROM ".$tabulka_rels." WHERE postid = '".rpd_sql_nice(get_the_ID())."';";
			$pribuzne   = $wpdb->get_results($sql);
			foreach ( $pribuzne as $pribuzny ) 
				{
					$zaskrtni[($pribuzny->wordid)] = 1;
				}

			$i          = 0;
			$jsaddition = "";

			//nacitame si vsetky odkazy
			$not_statement = false;			//je false, lebo musime sql spravit 2x
			$pridaj_not    = "";			//na zaciatku nie je NOT, lebo chceme zobrazit iba tie, co mame zaskrtnute!
			while($not_statement===false)
				{
					$sql        = "SELECT id, url, text_odkazu, atribut, ciel, trieda FROM ".$tabulka_linky." WHERE id ".$pridaj_not."IN (SELECT wordid FROM ".$tabulka_rels." WHERE postid = '".rpd_sql_nice(get_the_ID())."' ) ORDER BY url ASC, text_odkazu ASC;";
					$zaznamy    = $wpdb->get_results($sql);

					if($zaznamy)
						{
							echo "
";
							foreach ( $zaznamy as $zaznam ) 
								{
									$i++;
									if($zaskrtni[($zaznam->id)]==1)
										$checked = " checked=\"checked\" ";
											else
												$checked = "";
									echo "<input type=\"checkbox\" name=\"rpd_link[]\" id=\"chx".$i."\" value=\"".rpd_sql_nice($zaznam->id)."\" title=\"".rpd_sql_nice($zaznam->text_odkazu)." ".rpd_sql_nice($zaznam->url)."\" class=\"hidme\" ".$checked."/> <label for=\"chx".$i."\" id=\"l_chx".$i."\" class=\"label_move\">".rpd_sql_nice($zaznam->text_odkazu)." - <a href=\"".rpd_sql_nice($zaznam->url)."\" target=\"_blank\">".rpd_sql_nice($zaznam->url)."</a></label>";
								}
							echo "
<script type=\"text/javascript\">
var pocet_chbx = ".$i.";
</script>
";
						}
					if($pridaj_not=="NOT ")
						$not_statement = true;		//teraz skoncime vo while
					if($not_statement === false)
						{
							$pridaj_not = "NOT ";
							echo "<div id=\"ac_1\">";
						}
				}

			echo "</div>
";
			if($i==0)
				echo "No link found, make sure you add some first in \"<b>Tools/Related pages</b>\" menu, please.";

			$header_titul = rpd_get_setting("header");
			if(empty($header_titul))
				$header_titul = DEF_RPD_TEXT;

			//echo get_the_ID();
			echo "<hr class=\"style-one\"/><label for=\"force_title_field\">Moreover you can specify what text to show above below-selected links within your page/post. If you don't specify anything, default text will be shown (\"".$header_titul."\").</label>
				<input type=\"text\" id=\"force_title_field\" name=\"force_title_field\" value=\"".rpd_ziskaj_nadpis(get_the_ID())."\" size=\"".$dlzka_text."\" placeholder=\"This is '".DEF_RPD_TEXT."' by default\" />
				<hr class=\"style-one\"/><ol>
					<li><b>If no links are specified, your page/post will not contain contents of this plugin.</b></li>
					<li><b>If you want to modify look of links shown by this plugin, there's a CSS file named <i>style_rpd.css</i> which is located in this plugin's folder.</b></li>
					<li><b>Basic version will add a nofollow link pointing to plugin's official page to the end of related pages list. Such a link is perfectly SEO friendly and does comply with guidelines given <a href=\"http://support.google.com/webmasters/bin/answer.py?hl=en&amp;answer=96569\" target=\"_blank\">by Google</a> or Bing as it doesn't pass any value to the destination URL!</b></li></ol>
";
		}

	/* ulozime veci, ked clovek updatne alebo savne post/page */
	function relatedpages_save_postdata( $post_id )
		{
			global $tabulka_linky, $tabulka_rels, $tabulka_names, $wpdb;

			// verify if this is an auto save routine. 
			// If it is our form has not been submitted, so we dont want to do anything
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
			return;
			// verify this came from the our screen and with proper authorization,
			// because save_post can be triggered at other times

			if ( !isset( $_POST['relatedpages_noncename'] ) || !wp_verify_nonce( $_POST['relatedpages_noncename'], plugin_basename( __FILE__ ) ) )
			return;
			// Check permissions
			if ( 'page' == $_POST['post_type'] ) 
				{
					if ( !current_user_can( 'edit_page', $post_id ) )
					return;
				}
					else
						{
							if ( !current_user_can( 'edit_post', $post_id ) )
							return;
						}
			// OK, we're authenticated: we need to find and save the data

			//ziskame post_id
			$post_ID = $_POST['post_ID'];

			//ulozime vsetky vybrane linky do tabulky
			$pole = $_POST['rpd_link'];
			$cnt  = count($pole);

			//najprv ale premazeme vsetky predosle odkazy
			$sql  = "DELETE FROM ".$tabulka_rels." WHERE postid = '".rpd_sql_prepare($post_ID)."';";
			$wpdb->query($sql);
			rpd_opt_tables($tabulka_rels);

			//uz samotne zapisovanie
			for($i=0;$i<$cnt;$i++)
				{
					//echo $pole[$i]."<br />";
					$sql = "INSERT INTO ".$tabulka_rels." VALUES ( '".rpd_sql_prepare($post_ID)."' , '".rpd_sql_prepare($pole[$i])."' );";
					$wpdb->query($sql);
				}

			//este ulozime text pre related pages - ale iba vtedy, ak je rozdielne od defaultneho nastavenia!
			$titul = $_POST['force_title_field'];
//			if(empty($titul))
//				$titul = DEF_RPD_TEXT;

			if(!empty($titul))
				{
					if($titul==DEF_RPD_TEXT)
						$sql = "DELETE FROM ".$tabulka_names." WHERE id = '".rpd_sql_prepare($post_ID)."';";
							else
								$sql   = "REPLACE INTO ".$tabulka_names." VALUES ( '".rpd_sql_prepare($post_ID)."' , '".rpd_sql_prepare($titul)."' ); ";
					$wpdb->query($sql);
				}
					else
						{
							$sql   = "DELETE FROM ".$tabulka_names." WHERE id = '".rpd_sql_prepare($post_ID)."';";
							$wpdb->query($sql);
							rpd_opt_tables($tabulka_names);
						}
		}

	/* vymazeme veci, ked clovek zmaze post/page */
	function relatedpages_delete_postdata( $post_id )
		{
			global $tabulka_linky, $tabulka_rels, $tabulka_names, $wpdb;

			//iba administrator moze mazat
			if(current_user_can('administrator'))
				{
					//ziskame post_id
					$post_ID = $post_id;

					//premazeme vsetky predosle odkazy
					$sql  = "DELETE FROM ".$tabulka_rels." WHERE postid = '".rpd_sql_prepare($post_ID)."';";
					$wpdb->query($sql);

					//premazeme titul pre related pages
					$sql  = "DELETE FROM ". $tabulka_names." WHERE id = '".rpd_sql_prepare($post_ID)."';";
					$wpdb->query($sql);

					rpd_opt_tables($tabulka_rels);
					rpd_opt_tables($tabulka_names);
				}
		}

	//zistime ciel pre odkaz, pripadne vynechame, ak je default
	function rpd_zisti_ciel($in)
		{
			if(strtolower($in)!='default')
				$return = " target=\"".$in."\" ";
					else
						$return = "";
			return $return;
		}

	//pridame medzeru pred a po atribute
	function rpd_zisti_atribut($in)
		{
			if(!empty($in))
				$return = " ".$in." ";
					else
						$return = "";
			return $return;
		}

	//zistime triedu pre odkaz, pripadne vynechame, ak je default
	function rpd_zisti_triedu($in)
		{
			if(!empty($in))
				$return = " class=\"".$in."\" ";
					else
						$return = "";
			return $return;
		}

	//samotna funkcia na pridavanie related pages do dokumentov
	function zobraz_pribuzne_stranky($content)
		{
			global $tabulka_linky, $tabulka_rels, $tabulka_names, $wpdb;
			$post_ID = get_the_ID();

			//ziskame text... ak nie je ziaden, dame mu default
			$sql   = "SELECT force_name FROM ".$tabulka_names." WHERE id = '".rpd_sql_prepare($post_ID)."';";
			$titul = $wpdb->get_var($sql);

			if(empty($titul))
				$titul = "<h4 class=\"rpd_h4\">".DEF_RPD_TEXT."</h4>";
					else
						$titul = "<h4 class=\"rpd_h4\">".$titul."</h4>";

			//ziskame linky - ak nenajdeme ziadne, potom nezobrazujeme nic na konci clanku!
			$sql     = "SELECT url, text_odkazu, atribut, ciel, trieda FROM ".$tabulka_linky." WHERE id IN (SELECT wordid FROM ".$tabulka_rels." WHERE postid = '".rpd_sql_prepare($post_ID)."') ORDER BY text_odkazu ASC, url ASC;";
			$zaznamy = $wpdb->get_results($sql);

			if($zaznamy)
				{
					$obsah = $titul."<ol class=\"rpd_ol_single_column\">
";
					foreach ( $zaznamy as $zaznam ) 
						{
							if(!isset($max_dlzka_textu))
								$max_dlzka_textu = strlen(rpd_sql_nice($zaznam->text_odkazu));
									else
										{
											if( strlen(rpd_sql_nice($zaznam->text_odkazu)) > $max_dlzka_textu )
												$max_dlzka_textu = strlen(rpd_sql_nice($zaznam->text_odkazu));
										}
							$obsah .= "<li class=\"rpd_li\"><a href=\"".rpd_sql_nice($zaznam->url)."\"".rpd_zisti_ciel(rpd_sql_nice($zaznam->ciel)).rpd_zisti_atribut(rpd_sql_nice($zaznam->atribut)).rpd_zisti_triedu(rpd_sql_nice($zaznam->trieda)).">".rpd_sql_nice($zaznam->text_odkazu)."</a></li>
";
						}
					$obsah .= "</ol>
";

					$obsah .= "<small id=\"small_font\">powered by <a href=\"http://www.janhvizdak.com/related-pages-wordpress-plugin.html\" rel=\"nofollow\" target=\"_blank\" title=\"Officiel page of Related Pages plugin\">Related Pages WP plugin</a></small>";
				}
					else
						$obsah = "";

			return $content.$obsah;
		}

	//vytvorime tabulky
	vytvor_tabulky();

	//pripojime styly
	add_action('admin_head', 'rpd_stylesheet');

	//pridame do obsahu
	add_filter('the_content','zobraz_pribuzne_stranky',PRI_RPD_DEF);

	//este pridame css styly pre beznych navstevnikov, nech tie odkazy su formatovane pekne
	add_action('wp_enqueue_scripts', 'rpd_scripts_css');
?>
