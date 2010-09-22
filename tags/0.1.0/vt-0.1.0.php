#!/usr/local/bin/php -q
<?php
/* $Id: vt-0.1.0.php,v 1.1 2001/12/15 13:28:06 mrfrost Exp $ */

/*
 *   php_voctrain - a simple vocabular trainer
 *
 *   Copyright (C) 2001 Frank Thomas <frank@thomas-alfeld.de>
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program; if not, write to the Free Software
 *   Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 */

error_reporting (E_ALL);

$version     = "0.1.0";
$script_name = "php_voctrain";

// here you can specify the default voc file to use at starup
$voc_file    = "latin.xml";

$vocs_first  = array();
$vocs_second = array();
$lang        = array("first"  => '',
                     "second" => '');

$in_chapter      = FALSE;
$chapter_to_read = "";

dl('php_gtk.' . (strstr(PHP_OS, 'WIN') ? 'dll' : 'so'));

////////////////////////////////////////////////////////////////////////////////

function destroy()
{
   Gtk::main_quit();
}

////////////////////////////////////////////////////////////////////////////////

function close_window($widget)
{
   $window = $widget->get_toplevel();
   $window->hide();
}

////////////////////////////////////////////////////////////////////////////////

function show_license()
{

   $window_license = &new GtkWindow();
   $window_license->show();
   $window_license->set_usize(500, 400);
   $window_license->set_border_width(10);
   $window_license->set_title(" GNU General Public License (GPL) ");

      $box_license = &new GtkVBox();
      $window_license->add($box_license);

         $scrolled_win = &new GtkScrolledWindow();
         $scrolled_win->set_policy(GTK_POLICY_AUTOMATIC, GTK_POLICY_ALWAYS);
         $box_license->pack_start($scrolled_win, TRUE, TRUE, 1);
         $scrolled_win->show();

	    $text_license = &new GtkText(NULL, NULL);
	    $text_license->set_editable(FALSE);
	    $text_license->set_word_wrap(80);
	    //$text_license->set_line_wrap(FALSE);
	    $scrolled_win->add($text_license);


   if (!@fopen("COPYING", "r"))
      $content = "COPYING could not be opened.";

   else
   {
      $fp = fopen("COPYING", "r");
      $content = fread($fp, filesize("COPYING"));
      fclose($fp);
   }

   $text_license->insert(NULL, NULL, NULL, $content, -1);

      $bbox = &new GtkHButtonBox();
      $bbox->set_layout(GTK_BUTTONBOX_SPREAD);
      $bbox->set_border_width(10);
      $box_license->pack_end($bbox, FALSE, FALSE, 0);

         $close = &new GtkButton("Close");
         $close->connect('clicked', 'close_window');
         $bbox->add($close);

   $window_license->show_all();

}

////////////////////////////////////////////////////////////////////////////////

function show_about()
{
   global $version,
          $script_name;

   $window_about = &new GtkWindow();
   $window_about->set_title(" About $script_name ");
   $window_about->set_usize(350, 250);
   $window_about->set_policy(FALSE, FALSE, FALSE);

      $box = &new GtkVBox();
      $box->set_border_width(10);
      $window_about->add($box);

        $string = "$script_name $version\n\nA PHP-Gtk Vocabular Trainer\n\n(C) 2001 Frank Thomas\nfrank@thomas-alfeld.de\n\nhttp://www.thomas-alfeld.de/frank/";

        $label = &new GtkLabel($string);
        $box->pack_start($label, TRUE, TRUE, 1);

        $sep = &new GtkHSeparator();
        $box->add($sep);

        $bbox = &new GtkHButtonBox();
        $bbox->set_layout(GTK_BUTTONBOX_SPREAD);
        $bbox->set_border_width(10);
        $box->pack_end($bbox, FALSE, FALSE, 0);

           $close = &new GtkButton("Close");
           $close->connect('clicked', 'close_window');
           $bbox->add($close);


   $window_about->show_all();
}

////////////////////////////////////////////////////////////////////////////////

function cancel_dialog()
{
   return true;
}

////////////////////////////////////////////////////////////////////////////////

function get_voc_file($button, $fs)
{
   global $voc_file, $voc_entry;
   $voc_file = $fs->get_filename();
   $voc_entry->set_text(basename($voc_file));

   read_chapters_from_file();
   get_languages();
   get_vocs();

   return true;
}

////////////////////////////////////////////////////////////////////////////////

function load_file()
{
   $fs = &new GtkFileSelection("Load Vocabular File");

   $ok_b = $fs->ok_button;
   $ok_b->connect('clicked', 'get_voc_file', $fs);
   $ok_b->connect_object('clicked', array($fs, 'destroy'));

   $cancel_b = $fs->cancel_button;
   $cancel_b->connect('clicked', 'cancel_dialog', $fs);
   $cancel_b->connect_object('clicked', array($fs, 'destroy'));

   $fs->show();
}

////////////////////////////////////////////////////////////////////////////////

function ifchapter($parser, $name, $attribs)
{
   global $chapter_entry;

   if ($name == "CHAPTER")
   {
      $item = &new GtkListItem($attribs["NAME"]);
      $item->show();

      $items = array();
      array_unshift($items, $item);

      $list = $chapter_entry->list;
      $list->append_items($items);
   }

   return TRUE;
}

////////////////////////////////////////////////////////////////////////////////

function void($parser, $name)
{
   return TRUE;
}

////////////////////////////////////////////////////////////////////////////////

function read_chapters_from_file()
{
   global $voc_file, $chapter_entry;

   $list = $chapter_entry->list;
   $list->clear_items(0, -1);

   $entry = $chapter_entry->entry;
   $entry->set_text("");


   if (!($fp = @fopen($voc_file, "r")))
   {
      $list = $chapter_entry->list;
      $list->clear_items(0, -1);

      $entry = $chapter_entry->entry;
      $entry->set_text("");

      $item = &new GtkListItem("$voc_file could not be opened.");
      $item->show();
      $items = array($item);

      $list->append_items($items);
      return FALSE;
   }

   $xml_parser = xml_parser_create();
   xml_set_element_handler($xml_parser, "ifchapter", "void");

   while($data = fread($fp, 4096))
   {
      xml_parse($xml_parser, $data);
   }

   xml_parser_free($xml_parser);
   return TRUE;
}

////////////////////////////////////////////////////////////////////////////////

function language_xml($parser, $name, $attribs)
{
   global $lang, $radio_label1, $radio_label2;

   if ($name == "LANGUAGE")
   {
      $lang['first']  = $attribs['FIRST'];
      $lang['second'] = $attribs['SECOND'];
   }

   $radio_label1->set_text($lang['first']);
   $radio_label2->set_text($lang['second']);

   return TRUE;
}

////////////////////////////////////////////////////////////////////////////////

function get_languages()
{
   global $voc_file, $lang, $radio_label1, $radio_label2;


   $lang['first']  = "ERROR - could not get language";
   $lang['second'] = "ERROR - could not get language";

   if (!($fp = @fopen($voc_file, "r")))
   {
      $lang['first']  = "ERROR - could not get language";
      $lang['second'] = "ERROR - could not get language";

      $radio_label1->set_text("$lang[first]");
      $radio_label2->set_text("$lang[second]");

      return FALSE;
   }

   $xml_parser = xml_parser_create();
   xml_set_element_handler($xml_parser, "language_xml", "void");

   while($data = fread($fp, 4096))
   {
      xml_parse($xml_parser, $data);
   }

   xml_parser_free($xml_parser);
   //return $lang;

   $radio_label1->set_text("$lang[first]");
   $radio_label2->set_text("$lang[second]");

   return TRUE;
}

////////////////////////////////////////////////////////////////////////////////

function entry_s_editable($radio)
{
   global $first_entry;
   $first_entry->set_editable($radio->get_active());

   if ($radio->get_active() == 1)
      get_vocs();
}

function entry_f_editable($radio)
{
   global $second_entry;
   $second_entry->set_editable($radio->get_active());

   if ($radio->get_active() == 1)
      get_vocs();
}

////////////////////////////////////////////////////////////////////////////////

function get_voc_all($parser, $name, $attribs)
{
   global $vocs_first,
          $vocs_second;

   if ($name == "VOC")
   {
     $vocs_first[]  = $attribs["FIRST"];
     $vocs_second[] = $attribs["SECOND"];
   }

   return TRUE;
}

////////////////////////////////////////////////////////////////////////////////

function get_voc_chapter($parser, $name, $attribs)
{
   global $chapter_to_read, $in_chapter,
          $vocs_first, $vocs_second;

   if($name == "CHAPTER" and $attribs["NAME"] == $chapter_to_read)
      $in_chapter = TRUE;

   if($in_chapter == TRUE and $name == "VOC")
   {
      $vocs_first[]  = $attribs["FIRST"];
      $vocs_second[] = $attribs["SECOND"];
   }

   return TRUE;
}

////////////////////////////////////////////////////////////////////////////////

function chapter_end($parser, $name)
{
   global $in_chapter;

   if($name == "CHAPTER" and $in_chapter == TRUE)
      $in_chapter = FALSE;

   return TRUE;
}

////////////////////////////////////////////////////////////////////////////////

function show_vocs()
{
   global $vocs_first, $vocs_second,
          $radiobutton1, $radiobutton2,
          $first_entry, $second_entry,
          $cur_first_voc, $cur_second_voc;

   //$first_lang = $second_lang = FALSE;

   if (empty($vocs_first))
      return FALSE;

   srand((double)microtime()*1000000);
   $random = rand(0,(count($vocs_first)-1));

   $cur_first_voc  = $vocs_first[$random];
   $cur_second_voc = $vocs_second[$random];

   if($radiobutton1->get_active() == 1)
   {
      //$first_lang = TRUE;
      if (!empty($vocs_first[$random]))
         $first_entry->set_text($vocs_first[$random]);
   }

   else if($radiobutton2->get_active() == 1)
   {
      //$second_lang = TRUE;
      if (!empty($vocs_second[$random]))
         $second_entry->set_text($vocs_second[$random]);
   }
   return TRUE;
}

////////////////////////////////////////////////////////////////////////////////

function get_vocs()
{
   global $voc_file,
          $first_entry, $second_entry,
          $chapter_entry,
          $vocs_first, $vocs_second,
          $chapter_to_read;

   unset($GLOBALS['vocs_first']);
   unset($GLOBALS['vocs_second']);

   $first_entry->set_text("");
   $second_entry->set_text("");

   $first_lang = $second_lang = FALSE;

   $entry = $chapter_entry->entry;
   $current_chapter = $entry->get_text();

   if (!($fp = @fopen($voc_file, "r")))
      return FALSE;

   if ($current_chapter == "All")
   {
      $xml_parser = xml_parser_create();
      xml_set_element_handler($xml_parser, "get_voc_all", "void");

      while($data = fread($fp, 4096))
      {
         xml_parse($xml_parser, $data);
      }
      xml_parser_free($xml_parser);

      show_vocs();

   }

   if ($current_chapter != "All")
   {
      $chapter_to_read = $current_chapter;

      $xml_parser = xml_parser_create();
      xml_set_element_handler($xml_parser, "get_voc_chapter", "chapter_end");

      while($data = fread($fp, 4096))
      {
         xml_parse($xml_parser, $data);
      }
      xml_parser_free($xml_parser);

      show_vocs();
   }

}

////////////////////////////////////////////////////////////////////////////////

function compare_vocs($original, $user_input, $shown_voc)
{
   global $both_voc, $right_wrong;

   $orig_vocs = explode(",", $original);

   foreach($orig_vocs as $orig)
   {
      $orig = trim($orig);

      if( strtolower($orig) == strtolower($user_input) and !empty($orig) )
      {
         $both_voc->set_text("$shown_voc - $original");
         $right_wrong->set_text(" RIGHT ");

         show_vocs();
         return TRUE;
      }

   }

   if(!empty($orig))
   {
      $both_voc->set_text("$shown_voc - $original");
      $right_wrong->set_text(" WRONG ");
   }

   show_vocs();
   return TRUE;
}

////////////////////////////////////////////////////////////////////////////////

function compare()
{
   global $cur_first_voc, $cur_second_voc,
          $radiobutton1, $radiobutton2,
          $first_entry, $second_entry;

   if ($radiobutton1->get_active() == 1)
   {
      $user_input = $second_entry->get_text();

      compare_vocs($cur_second_voc, $user_input, $cur_first_voc);
      $second_entry->set_text("");
      unset($user_input);
   }

   if ($radiobutton2->get_active() == 1)
   {
      $user_input = $first_entry->get_text();

      compare_vocs($cur_first_voc, $user_input, $cur_second_voc);
      $first_entry->set_text("");
      unset($user_input);
   }

   return TRUE;
}

////////////////////////////////////////////////////////////////////////////////

$window = &new GtkWindow();
$window->set_title(" $script_name (Version $version) ");
$window->connect('destroy', 'destroy');
$window->set_usize(430, 330);

   $box = &new GtkVBox();
   $box->show();
   $window->add($box);

      $handlebox = &new GtkHandleBox();
      $menubar = &new GtkMenuBar();

      $box->pack_start($handlebox, FALSE, FALSE, 0);
      $handlebox->add($menubar);


      $file_menu_item = &new GtkMenuItem("File");

	 $file_menu = &new GtkMenu();

         $file_menu_load = &new GtkMenuItem("Load");
         $file_menu_load->connect('activate', 'load_file');
         $file_menu->append($file_menu_load);

         $file_menu_quit = &new GtkMenuItem("Quit");
         $file_menu_quit->connect('activate', 'destroy');
         $file_menu->append($file_menu_quit);

         $file_menu_item->set_submenu($file_menu);
         $menubar->append($file_menu_item);



      $help_menu_item = &new GtkMenuItem("Help");

	 $help_menu = &new GtkMenu();

	    $help_menu_license = &new GtkMenuItem("License");
	    $help_menu_license->connect('activate', 'show_license');
	    $help_menu->append($help_menu_license);

	    $help_menu_about = &new GtkMenuItem("About");
         $help_menu_about->connect('activate', 'show_about');
	    $help_menu->append($help_menu_about);

         $help_menu_item->set_submenu($help_menu);
         $menubar->append($help_menu_item);

   $box2 = &new GtkHBox();
   $box2->set_border_width(15);
   $box->add($box2);

      $box3 = &new GtkVBox();
      $box3->set_border_width(5);
      $box2->pack_start($box3, TRUE, TRUE, 0);

         $voc_label = &new GtkLabel("Vocabular File:");
         $voc_label->set_justify(GTK_JUSTIFY_LEFT);
         $box3->add($voc_label);

         $voc_entry = &new GtkEntry();
         $voc_entry->set_text($voc_file);
         $voc_entry->set_editable(FALSE);
         $box3->add($voc_entry);


      $box4 = &new GtkVBox();
      $box4->set_border_width(5);
      $box2->pack_start($box4, TRUE, TRUE, 0);

         $chapter_label = &new GtkLabel("Chapter:");
         $chapter_label->set_justify(GTK_JUSTIFY_LEFT);
         $box4->add($chapter_label);

         $chapter_entry = &new GtkCombo();
         read_chapters_from_file();

         $entry = $chapter_entry->entry;
         $entry->connect('activate', 'get_vocs');

         $combolist = $chapter_entry->list;
         $combolist->connect('button_press_event', 'get_vocs');

         $box4->add($chapter_entry);


      $frame = &new GtkFrame('Choose Language');
      $frame->set_border_width(7);
      //$frame->set_shadow_type(GTK_SHADOW_IN);
      $box->add($frame);

         $box5 = &new GtkVBox();
         $box5->set_border_width(5);
         $frame->add($box5);


            $radiobutton1 = &new GtkRadioButton(NULL, '');
            $radiobutton1->connect('toggled', 'entry_f_editable');
            $radio_label1 = $radiobutton1->child;
            $radio_label1->set_text("$lang[first]");
            $radiobutton1->set_active(TRUE);
            $box5->add($radiobutton1);


            $radiobutton2 = &new GtkRadioButton($radiobutton1, '');
            $radiobutton2->connect('toggled', 'entry_s_editable');
            $radio_label2 = $radiobutton2->child;
            $radio_label2->set_text("$lang[second]");
            $radiobutton2->set_active(FALSE);
            $box5->add($radiobutton2);

            get_languages();

      $frame_voc = &new GtkFrame();
      $frame_voc->set_border_width(7);
      $box->add($frame_voc);

         $box6h = &new GtkHBox();
         $box6h->set_border_width(5);
         $frame_voc->add($box6h);

            $box6v = &new GtkVBox();
            $box6v->set_border_width(5);
            $box6h->pack_start($box6v, TRUE, TRUE, 1);

               $first_entry = &new GtkEntry();
               $first_entry->set_editable(FALSE);
               $first_entry->connect('activate', 'compare');
               $box6v->add($first_entry);
               $first_entry->show();

               $second_entry = &new GtkEntry();
               $second_entry->set_editable(TRUE);
               $second_entry->connect('activate', 'compare');
               $box6v->add($second_entry);
               $second_entry->show();

               get_vocs();

            $box7v = &new GtkVBox();
            $box7v->set_border_width(5);
            $box6h->pack_end($box7v, TRUE, TRUE, 1);

               $ok_button = &new GtkButton(" Compare ");
               $ok_button->connect('clicked', 'compare');
               $box7v->add($ok_button);

      $frame_comp = &new GtkFrame("Result");
      $frame_comp->set_border_width(7);
      $box->add($frame_comp);

         $boxh_comp = &new GtkHBox();
         $boxh_comp->set_border_width(5);
         $frame_comp->add($boxh_comp);

            /*

            $window->realize();

            $pix_wrong = array("54 22 3 1",
                               " 	c None",
                               ".	c #DEDEDE",
                               "+	c #910505",
                               "......................................................",
                               "......................................................",
                               "......................................................",
                               "......................................................",
                               "......................................................",
                               "......................................................",
                               ".....++....++.+++++++....++++...++....++...+++++......",
                               ".....++....++.++....++..++..++..+++...++..++...++.....",
                               ".....++....++.++....++.++....++.++++..++.++...........",
                               ".....++....++.++....++.++....++.++++..++.++...........",
                               ".....++.++.++.+++++++..++....++.++.++.++.++...........",
                               ".....++.++.++.+++++....++....++.++.++.++.++...+++.....",
                               ".....++.++.++.++..++...++....++.++..++++.++....++.....",
                               ".....++++++++.++...++..++....++.++...+++.++....++.....",
                               ".....+++..+++.++....++..++..++..++...+++..++...++.....",
                               ".....++....++.++....++...++++...++....++...+++++......",
                               "......................................................",
                               "......................................................",
                               "......................................................",
                               "......................................................",
                               "......................................................",
                               "......................................................");

            $pix_right = array("51 22 3 1",
                               " 	c None",
                               ".	c #DEDEDE",
                               "+	c #039B03",
                               "...................................................",
                               "...................................................",
                               "...................................................",
                               "...................................................",
                               "...................................................",
                               "...................................................",
                               "....+++++++...++++++....+++++..++....++.++++++++...",
                               "....++....++....++.....++...++.++....++....++......",
                               "....++....++....++....++.......++....++....++......",
                               "....++....++....++....++.......++....++....++......",
                               "....+++++++.....++....++.......++++++++....++......",
                               "....+++++.......++....++...+++.++....++....++......",
                               "....++..++......++....++....++.++....++....++......",
                               "....++...++.....++....++....++.++....++....++......",
                               "....++....++....++.....++...++.++....++....++......",
                               "....++....++..++++++....+++++..++....++....++......",
                               "...................................................",
                               "...................................................",
                               "...................................................",
                               "...................................................",
                               "...................................................",
                               "...................................................");

            $transparent = &new GdkColor(0, 0, 0);

            list($pixmap_right, $mask_right) = Gdk::pixmap_create_from_xpm_d($window->window, $transparent, $pix_right);
            list($pixmap_wrong, $mask_wrong) = Gdk::pixmap_create_from_xpm_d($window->window, $transparent, $pix_wrong);

            $comp_map = &new GtkPixmap($pixmap_right, $mask_right);
            $boxh_comp->pack_start($comp_map);

            */


            $right_wrong = &new GtkLabel(" Begin now! ");
            $boxh_comp->pack_start($right_wrong, FALSE, FALSE, 15);

            if ($lang['first'] != "ERROR - could not get language")
            {
               $both_voc = &new GtkLabel($lang['first']." - ".$lang['second']);
            }
            else
            {
               $both_voc = &new GtkLabel("no language - no language");
            }

            $boxh_comp->pack_end($both_voc, FALSE, FALSE, 20);



$window->show_all();

Gtk::main();
?>