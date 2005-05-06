#!/usr/bin/php -q
<?php
/* $Id: vt-0.2.1.php,v 1.1 2002/07/20 13:26:59 mrfrost Exp $ */

// Load the Gtk extension.
dl('php_gtk.'.(stristr(PHP_OS,'Win')?'dll':'so'));

$glo['gladefile'] = 'voctrain.glade'; 
$glo['version'] = '0.2.0'; 
$glo['licensefile'] = 'COPYING';
$glo['voctrain_url'] = 'http://www.thomas-alfeld.de/frank/index.php?file=MyOpenSource/php_voctrain';

class vt_gui {

	var $gladefile;
	var $version;
	var $licensefile;
	var $voctrain_url;

	function vt_gui(){
		global $glo;
		$this->gladefile = $glo['gladefile'];
		$this->version = $glo['version'];
		$this->licensefile = $glo['licensefile'];
		$this->voctrain_url = $glo['voctrain_url'];
		$this->init_main_window();
	}
	
	function mainquit(){
		Gtk::main_quit();
	}
	
	function close_window($widget){
		$window = $widget->get_toplevel();
		$window->hide();
	}

	function init_main_window(){
		$main_glade = &new GladeXML($this->gladefile, 'main_window');
		$main_glade->signal_connect('destroy_window', array($this, 'mainquit'));
		$main_window = $main_glade->get_widget('main_window');
		$main_window->set_title("voctrain");
		$this->create_main_menu($main_glade);
	}
	
	function create_main_menu($glade_object){
		$file_open = $glade_object->get_widget('file_open');
		$file_preferences = $glade_object->get_widget('file_preferences');
		$file_quit = $glade_object->get_widget('file_quit');
		
		$file_open->connect('activate', array($this, ''));
		$file_preferences->connect('activate', array($this, ''));
		$file_quit->connect('activate', array($this, 'mainquit'));
		
		$help_license = $glade_object->get_widget('help_license');
		$help_homepage = $glade_object->get_widget('help_homepage');
		$help_about = $glade_object->get_widget('help_about');
						
		$help_license->connect('activate', array($this, 'create_license_window'));
		$help_homepage->connect('activate', array($this, 'launch_url'), $this->voctrain_url);
		$help_about->connect('activate', array($this, 'create_about_window'));
	}
	
	function create_about_window($widget){
		$about_glade = &new GladeXML($this->gladefile, 'about_window');	
		$app_label = $about_glade->get_widget('app_label');
		$app_label->set_text("voctrain $this->version");
		$close_button = $about_glade->get_widget('close_button');
		$close_button->connect('clicked', array($this, 'close_window'));
	}
	
	function create_license_window($widget){
		$license_glade = &new GladeXML($this->gladefile, 'license_window');
		$close_button = $license_glade->get_widget('close_button');
		$close_button->connect('clicked', array($this, 'close_window'));
	
		if ( ($fp = fopen($this->licensefile,'r')) ) {
			$license = fread($fp, filesize($this->licensefile));
			fclose($fp);
		} else {
			$license = "Could not open file $this->licensefile.";
		}
		
		$text_area = $license_glade->get_widget('license_text');
		// font, foreground color, background color, text, length
		$text_area->insert(NULL, NULL, NULL, $license, -1);
	}
	
	function launch_url($widget, $url) {
	
	}

}

// Initialize the main gui.
$gui = new vt_gui();

// Run the Gtk mainloop.
Gtk::main();

?>
