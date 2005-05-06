#!/usr/bin/env python
#
#   php_voctrain - A simple Gtk+ vocabular trainer
#
#   Copyright (C) 2001-2002 Frank Thomas <frank@thomas-alfeld.de>
#
#   This program is free software; you can redistribute it and/or modify
#   it under the terms of the GNU General Public License as published by
#   the Free Software Foundation; either version 2 of the License, or
#   (at your option) any later version.
#
#   This program is distributed in the hope that it will be useful,
#   but WITHOUT ANY WARRANTY; without even the implied warranty of
#   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#   GNU General Public License for more details.
#
#   You should have received a copy of the GNU General Public License
#   along with this program; if not, write to the Free Software
#   Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
#
#   $Id: vt-0.2.1.py,v 1.3 2002/11/09 18:45:46 mrfrost Exp $

# verbose = 1 for more ouput
verbose = 0

gladefile = 'voctrain.glade'
version = '0.2.1'
licensefile = 'COPYING' # Praise the GPL!
voctrain_url = 'http://www.thomas-alfeld.de/frank/index.php?file=MyOpenSource/php_voctrain'

import gnome

# app_id and app_version are used in the GnomeAbout widget.
gnome.app_id = 'voctrain'
gnome.app_version = version

import libglade, gnome.ui, gnome.url, gnome.config
import string, gtk, gnome.help

from gtk import *
from string import *
from random import *
#from inspect import *

from xml.sax import saxutils
from xml.sax import make_parser

###############################################################################

class vt_conf:

    def __init__(self):
	self.dvt_file = gnome.config.get_string(gnome.app_id + '/settings/dvt_file')
	if self.dvt_file == None:
	    gnome.config.set_string(gnome.app_id + '/settings/dvt_file', 'pvtml/latin.pvtml')
	    self.dvt_file = 'pvtml/latin.pvtml'
	    
	self.vt_file = gnome.config.get_string(gnome.app_id + '/settings/vt_file')
	if self.vt_file == None:
	    self.vt_file = self.dvt_file
	if self.give_short_vt_file() == '':
	    self.vt_file = self.dvt_file
	try:
	    fd = open(self.vt_file, 'r')
	    fd.close()
	except:
	    self.vt_file = self.dvt_file
	
	self.min_eq = gnome.config.get_string(gnome.app_id + '/settings/min_eq')
	if self.min_eq == None:
	    gnome.config.set_string(gnome.app_id + '/settings/min_eq', '50')
	    self.min_eq = '50'
		
	gnome.config.sync()

    def save_dvt_file(self, filename):
	self.dvt_file = filename
	gnome.config.set_string(gnome.app_id + '/settings/dvt_file', filename)
	gnome.config.sync()
	
    def save_vt_file(self, filename):
	self.vt_file = filename
	gnome.config.set_string(gnome.app_id + '/settings/vt_file', filename)
	gnome.config.sync()

    def save_min_eq(self, new_eq):
	self.min_eq = new_eq
	gnome.config.set_string(gnome.app_id + '/settings/min_eq', new_eq)
	gnome.config.sync()
	
    def give_short_vt_file(self):
	self.short_vt_file = split(self.vt_file, '/')[-1] 
	return self.short_vt_file

class vt_gui:

    def __init__(self):
        self.config = vt_conf()
        self.vt_xml = vt_xml(self.config.vt_file)
        self.init_main_window()

    def __str__(self):
        print 'vt_gui class'

    def close_window(self, widget, unknown=''):
        window = widget.get_toplevel()
        window.hide()

    def init_main_window(self):
        main_glade = libglade.GladeXML(gladefile, 'main_window')
        main_glade.signal_connect('destroy_window', mainquit)
        self.main_glade = main_glade
        main_window = main_glade.get_widget('main_window')
        main_window.set_title('voctrain')

        # Connect the search combo entry to it's callback. Also
        # the Gtk timer is added.
        search_entry      = main_glade.get_widget('search_entry')
        self.search_combo = main_glade.get_widget('search_combo')

        search_entry.connect('activate', self.query_vocs, 'SAVE_QUERY')
        gint = gtk.timeout_add(500, self.query_vocs, search_entry)
        self.query_text = ''

        self.query_o_text = main_glade.get_widget('query_o_text')
        self.query_o_text.set_word_wrap(FALSE)
        self.query_o_text.set_line_wrap(FALSE)

        self.query_t_text = main_glade.get_widget('query_t_text')
        self.query_t_text.set_word_wrap(FALSE)
        self.query_t_text.set_line_wrap(FALSE)

        # Initialize the pixmaps
        self.result_label = main_glade.get_widget('result_label')

        style = main_window.get_style()
        self.pixmap_ok, self.mask_ok = create_pixmap_from_xpm(main_window.get_window(),
                                       style.bg[STATE_NORMAL], './pixmaps/ok.xpm')

        self.pixmap_wrong, self.mask_wrong = create_pixmap_from_xpm(main_window.get_window(),
                                             style.bg[STATE_NORMAL], './pixmaps/wrong.xpm')

        self.result_pixmap = main_glade.get_widget('result_pixmap')
        self.result_pixmap.set(self.pixmap_ok, self.mask_ok)


        # Everything that needs to be initialized:
        self.rb_o_bool = TRUE
        self.rb_t_bool = FALSE

        self.create_main_menu(main_glade)
        self.status_bar = main_glade.get_widget('statusbar')
        self.status_bar.push(1, 'current file: ' + self.config.give_short_vt_file() )
        self.update_about_tab()
        self.update_lang_radiob()
        self.update_chapter_box()
        self.update_voc_entries()
        self.select_vocs()

        self.o_entry.connect('activate', self.check_input)
        self.t_entry.connect('activate', self.check_input)

    def query_vocs(self, widget, action=''):
        query = widget.get_text()
        result_o = []
        result_t = []

        if query != self.query_text:

            # This finds all similar strings in the original language.
            i=n=j=0
            for i in xrange(len(self.vt_xml.dh.vocabular)):
                for n in xrange(len(self.vt_xml.dh.vocabular[i]['o'])):
                    procentum = self.compute_similarity(query, self.vt_xml.dh.vocabular[i]['o'][n])
                    if procentum >= float(self.config.min_eq):
                        info_voc = {'pc'    : procentum,
                                    'voc_o' : self.vt_xml.dh.vocabular[i]['o'][n],
                                    'voc_t' : self.vt_xml.dh.vocabular[i]['t'][n]}
                        result_o.append(info_voc)

            result_o.sort()
            result_o.reverse()
            result_text = ''

            for j in xrange(len(result_o)):
                result_text = result_text + result_o[j]['voc_o'] + '  -  ' + result_o[j]['voc_t'] + '\n'

            length = self.query_o_text.get_length()
            self.query_o_text.backward_delete(length)
            self.query_o_text.insert(None, None, None, result_text)

            # This finds all similar strings in the training language.
            i=n=j=0
            for i in xrange(len(self.vt_xml.dh.vocabular)):
                for n in xrange(len(self.vt_xml.dh.vocabular[i]['t'])):
                    procentum = self.compute_similarity(query, self.vt_xml.dh.vocabular[i]['t'][n])
                    if procentum >= float(self.config.min_eq):
                        info_voc = {'pc'    : procentum,
                                    'voc_o' : self.vt_xml.dh.vocabular[i]['t'][n],
                                    'voc_t' : self.vt_xml.dh.vocabular[i]['o'][n]}
                        result_t.append(info_voc)

            result_t.sort()
            result_t.reverse()
            result_text = ''

            for j in xrange(len(result_t)):
                result_text = result_text + result_t[j]['voc_o'] + '  -  ' + result_t[j]['voc_t'] + '\n'

            length = self.query_t_text.get_length()
            self.query_t_text.backward_delete(length)
            self.query_t_text.insert(None, None, None, result_text)

        if action == 'SAVE_QUERY':
            item = gtk.GtkListItem(query)
            item.show()
            self.search_combo.list.add(item)

        self.query_text = query

        return TRUE

    # Computes the similarity between two strings.
    def compute_similarity(self, string1, string2):
        count_simil = 0
        ls1 = len(string1)
        ls2 = len(string2)

        if ls1 < ls2:
            cs1 = string1.lower()
            cs2 = string2.lower()
        else:
            cs1 = string2.lower()
            cs2 = string1.lower()

        for i in xrange(len(cs1)):
            if cs1[i] == cs2[i]:
                count_simil = count_simil + 1

        return float(count_simil)/(float(ls1+ls2)/2)*100

    def create_main_menu(self, glade_object):

	file_open = glade_object.get_widget('file_open')
	file_preferences = glade_object.get_widget('file_preferences')
	file_quit = glade_object.get_widget('file_quit')
	
	file_open.connect('activate', self.create_fs_dialog)
	file_preferences.connect('activate', self.create_propertybox)
	file_quit.connect('activate', mainquit)
	
	help_license = glade_object.get_widget('help_license')
	help_homepage = glade_object.get_widget('help_homepage')
	help_about = glade_object.get_widget('help_about')

	help_license.connect('activate', self.create_license_window)
	help_homepage.connect('activate', self.launch_url, voctrain_url)
	help_about.connect('activate', self.create_about_window)

    def create_about_window(self, widget):
	about_glade = libglade.GladeXML(gladefile, 'g_about_window')

    def create_license_window(self, widget):    
	license_glade = libglade.GladeXML(gladefile, 'license_window')
	close_button = license_glade.get_widget('close_button')
	close_button.connect('clicked', self.close_window)

	try: 
	    fd = open(licensefile, 'r')
	    license = fd.read()
	    fd.close()
	except:
	    license = 'COPYING could not be opened.'

	text_area = license_glade.get_widget('license_text')
	text_area.set_word_wrap(FALSE)
	text_area.set_line_wrap(FALSE)
	text_area.insert(None, None, None, license)

    def launch_url(self, widget, url):
	gnome.url.show(url)

    def create_fs_dialog(self, widget):
	fs_glade = libglade.GladeXML(gladefile, 'fileselection')
	fs_dialog = fs_glade.get_widget('fileselection') 

	fs_dialog.ok_button.connect('clicked', self.get_new_vt_file, fs_dialog)
	fs_dialog.cancel_button.connect('clicked', self.close_window)
	fs_dialog.connect('delete_event', self.close_window)

    def get_new_vt_file(self, widget, fs):
        vt_file = fs.get_filename()
        self.config.save_vt_file(vt_file)
        self.status_bar.push(1, 'current file: ' + self.config.give_short_vt_file() )
        fs.hide()

        # Everything that needs to be reloaded:
        self.vt_xml = vt_xml(vt_file)
        self.update_about_tab()
        self.update_lang_radiob()
        self.update_chapter_box()
        self.update_voc_entries()
        self.select_vocs()

    def create_propertybox(self, widget):
	proper_glade = libglade.GladeXML(gladefile, 'propertybox')
	propertybox = proper_glade.get_widget('propertybox')
	propertybox.set_title('voctrain Properties')
	
	propw = { 
	    'dvt_entry' : proper_glade.get_widget('dvt_entry'),
	    'min_eq_spin' : proper_glade.get_widget('min_eq_spin')  
	}

	proper_glade.signal_connect('on_propertybox_apply', self.apply_properties, propw)
	proper_glade.signal_connect('on_propertybox_help', self.help_window, 'property')

	propw['dvt_entry'].set_text(self.config.dvt_file)
	propw['min_eq_spin'].set_value(float(self.config.min_eq))

	gint = gtk.timeout_add(100, self.proove_property, propw, propertybox)
	propertybox.connect('destroy', self.remove_timer, gint)

    def proove_property(self, propw, propertybox):
	if propw['dvt_entry'].get_text() != self.config.dvt_file:
	    propertybox.changed()
	if propw['min_eq_spin'].get_value() != float(self.config.min_eq):
	    propertybox.changed()
	return TRUE
    
    def apply_properties(self, widget, gint, propw):
	# The first page.
	if gint == 0:
	    self.config.save_dvt_file(propw['dvt_entry'].get_text())
	    self.config.save_min_eq(str(propw['min_eq_spin'].get_value()))
	    
    def remove_timer(self, widget, gint):
        gtk.timeout_remove(gint)

    def help_window(self, widget, gint, topic):
	gnome.help.display('voctrain', topic + '.html')

    def update_about_tab(self):
	about_text = self.main_glade.get_widget('about_text')
	
	text_length = about_text.get_length()
	about_text.backward_delete(text_length)
	
	title            = self.vt_xml.give_title()
	author           = self.vt_xml.give_author()
	self.first_lang  = self.vt_xml.give_first_lang()
	self.second_lang = self.vt_xml.give_second_lang()
	n_lesson         = self.vt_xml.give_n_lesson()
	n_vocs           = self.vt_xml.give_n_vocs()

	try:
	    default = load_font('-monotype-courier new-medium-r-normal-*-12-*-*-*-m-*-iso8859-15')	
	except:
	    default = load_font('-misc-fixed-medium-r-normal-*-*-120-*-*-c-*-iso8859-15')
	
	if title != "":
	    about_text.insert(default, None, None, "\n Title  :  "+title)
	if author != "":
	    about_text.insert(default, None, None, "\n Author :  "+author)

	if self.first_lang != "" and self.second_lang != "":
	    about_text.insert(default, None, None, "\n\n Languages :  " + self.first_lang + ", " + self.second_lang)
	
	if n_lesson != str(0):
	    about_text.insert(default, None, None, "\n Lessons#  :  " + n_lesson)

	if n_vocs != "0 ()":
	    about_text.insert(default, None, None, "\n Vocs#     :  " + n_vocs)

    def update_lang_radiob(self):

	rb_o = self.main_glade.get_widget('rb_o')
	rb_t = self.main_glade.get_widget('rb_t')
        
	rb_o.set_active(gtk.TRUE)
	rb_o.connect('toggled', self.ex_radiob, 'rb_o')

	#rb_t.set_active(gtk.FALSE)
	rb_t.connect('toggled', self.ex_radiob, 'rb_t')
	
	first_lang  = self.vt_xml.give_first_lang()
	second_lang = self.vt_xml.give_second_lang()
	
	if first_lang == "":
	    first_lang = "unknown"
	if second_lang == "":
	    second_lang = "unknown"
	
	rb_o_label = rb_o.children()[0]
	rb_t_label = rb_t.children()[0]

	rb_o_label.set_text(first_lang)
	rb_t_label.set_text(second_lang)

    def ex_radiob(self, widget, radiob):
        if radiob == "rb_o":
            self.rb_o_bool = TRUE
            self.rb_t_bool = FALSE

        if radiob == "rb_t":
            self.rb_t_bool = TRUE
            self.rb_o_bool = FALSE

        # All that needs to be updated while changing the
        # radio buttons!
        self.update_voc_entries()

    # Adds all chapter to the combo box and clears old entries before.
    def update_chapter_box(self):
        self.chapter_entry = self.main_glade.get_widget('chapter_combo')
        self.chapter_entry.list.clear_items(0, -1)

        self.chapter_entry.list.connect('button_press_event', self.select_vocs)
        self.chapter_entry.entry.connect('activate', self.select_vocs)

        self.chapter_entry.entry.set_text('')

        item = gtk.GtkListItem('none')
        item.show()
        self.chapter_entry.list.add(item)

        for i in xrange (len(self.vt_xml.dh.vocabular)):
            chapter = self.vt_xml.dh.vocabular[i]['name']

            item = gtk.GtkListItem(chapter)
            item.show()
            self.chapter_entry.list.add(item)

    def update_voc_entries(self):
        o_label = self.main_glade.get_widget('o_label')
        t_label = self.main_glade.get_widget('t_label')

        if self.rb_t_bool and self.first_lang != '':
            o_label.set_text('< ' + self.first_lang + ' >')
        else:
            o_label.set_text(self.first_lang)

        if self.rb_o_bool and self.second_lang != '':
            t_label.set_text('< ' + self.second_lang + ' >')
        else:
            t_label.set_text(self.second_lang)

        self.o_entry = self.main_glade.get_widget('o_entry')
        self.t_entry = self.main_glade.get_widget('t_entry')

        if self.rb_o_bool:
            self.o_entry.set_editable(gtk.FALSE)
            self.t_entry.set_editable(gtk.TRUE)

        if self.rb_t_bool:
            self.o_entry.set_editable(gtk.TRUE)
            self.t_entry.set_editable(gtk.FALSE)

        # If we want to change the language, we also want new vocs.
        self.select_vocs()

        # This updates the language labels in the search tab.
        o_s_label = self.main_glade.get_widget('o_s_label')
        t_s_label = self.main_glade.get_widget('t_s_label')

        o_s_label.set_text(self.first_lang)
        t_s_label.set_text(self.second_lang)

    def select_vocs(self, widget='', gdk_event=''):
        try:
            seed(random())

            if self.chapter_entry.entry.get_text() == 'none':
                s_c = randrange(self.vt_xml.dh.n_lesson)
                s_n = randrange(len(self.vt_xml.dh.vocabular[s_c]['o']))

                self.right_o_voc = self.vt_xml.dh.vocabular[s_c]['o'][s_n]
                self.right_t_voc = self.vt_xml.dh.vocabular[s_c]['t'][s_n]

                if self.rb_o_bool:
                    self.t_entry.set_text('')
                    self.o_entry.set_text(self.right_o_voc)

                if self.rb_t_bool:
                    self.t_entry.set_text(self.right_t_voc)
                    self.o_entry.set_text('')

            else:
                for i in xrange(len(self.vt_xml.dh.vocabular)):
                    if self.vt_xml.dh.vocabular[i]['name'] == self.chapter_entry.entry.get_text():
                        s_c = i

                s_n = randrange(len(self.vt_xml.dh.vocabular[s_c]['o']))

                self.right_o_voc = self.vt_xml.dh.vocabular[s_c]['o'][s_n]
                self.right_t_voc = self.vt_xml.dh.vocabular[s_c]['t'][s_n]

                if self.rb_o_bool:
                    self.t_entry.set_text('')
                    self.o_entry.set_text(self.right_o_voc)

                if self.rb_t_bool:
                    self.t_entry.set_text(self.right_t_voc)
                    self.o_entry.set_text('')
        except:
            self.o_entry.set_text('')
            self.t_entry.set_text('')

    def check_input(self, widget):
        if self.rb_o_bool:
            input = self.t_entry.get_text().lower()

            if input == self.right_t_voc.lower():
                self.result_pixmap.set(self.pixmap_ok, self.mask_ok)
            else:
                self.result_pixmap.set(self.pixmap_wrong, self.mask_wrong)

            self.result_label.set_text(self.right_o_voc +'  -  '+ self.right_t_voc)

        if self.rb_t_bool:
            input = self.o_entry.get_text().lower()

            if input == self.right_o_voc.lower():
                self.result_pixmap.set(self.pixmap_ok, self.mask_ok)
            else:
                self.result_pixmap.set(self.pixmap_wrong, self.mask_wrong)

            self.result_label.set_text(self.right_t_voc +'  -  '+ self.right_o_voc)

        # Give me more words!
        self.select_vocs()

class vt_xml:

    def __init__(self, file):

        try:
            parser = make_parser()
            self.dh = parse_xml()
            parser.setContentHandler(self.dh)
            parser.parse(file)
            self.compute_vocs()

            if verbose:
                print "'" + split(file, '/')[-1] + "' loaded."
        except:
            if verbose:
                print "'" + split(file, '/')[-1] + "' is no valid pvtml file."

    def compute_vocs(self):
        for i in xrange(len(self.dh.vocabular)):
            no  = self.dh.vocabular[i]['num']
            inc = self.dh.vocabular[i]['inc']
            
            if inc != None:
                inc_val = split(inc, ',')
            else:
                inc_val = ''

            for key, value in self.dh.vocs.items():

                if no == key:
                    self.dh.vocabular[i]['o'] = value['o']
                    self.dh.vocabular[i]['t'] = value['t']

                # all included vocs are appended to the lists
                for inc_no in inc_val:
                    if int(inc_no) == key:
                        self.dh.vocabular[i]['o'][:0] = value['o']
                        self.dh.vocabular[i]['t'][:0] = value['t']

        if verbose:
            print self.dh.vocabular

    def give_title(self):
	return str(self.dh.title)
	
    def give_author(self):
	return str(self.dh.author)

    def give_first_lang(self):
	return str(self.dh.lang_o)
	
    def give_second_lang(self):
	return str(self.dh.lang_t)

    def give_n_lesson(self):
	return str(self.dh.n_lesson)

    def give_n_vocs(self):
	self.n = 0
	n_all = ""
	for i in xrange(len(self.dh.vocabular)):
	    current_n = int(len(self.dh.vocabular[i]['o']))
	    self.n = current_n + self.n

	    if n_all == "":
		n_all =	str(current_n)
	    else:
		n_all = n_all + "," + str(current_n)

	return str(self.n) + " (" + n_all + ")"


class parse_xml(saxutils.DefaultHandler):

    def __init__(self):
        self.title = ""
        self.author = ""
        self.lang_o = self.lang_t = ""
        self.lang_bool = self.lang_o_bool = self.lang_t_bool = 0
        self.lesson_bool = self.n_lesson = self.desc_bool = 0
        self.vocabular = []
        self.e_voc_bool = self.eo_voc_bool = self.et_voc_bool = 0
        self.vocs = {}
        self.i = 0

    def startElement(self, name, attrs):
        if name == 'pvtml':
            self.author = attrs.get('author', None)
            self.title = attrs.get('title', None)

        if name == 'languages':
            self.lang_bool = 1

        if self.lang_bool and name == 'o':
            self.lang_o_bool = 1

        if self.lang_bool and name == 't':
            self.lang_t_bool = 1

        if self.lesson_bool:
            self.n_lesson = self.n_lesson + 1

        if name == 'lesson':
            self.lesson_bool = 1

        if self.lesson_bool and name == 'desc':
            self.desc_bool  = 1
            self.lesson_no  = attrs.get('no', None)
            self.lesson_inc = attrs.get('inc', None)

            self.vocabular[len(self.vocabular):0] = [self.i]
            self.i = self.i + 1
	
	if name == 'e':
	    self.e_voc_bool = 1 
	    if attrs.get('m',None) != None:
		self.e_cur_lesson_no = int(attrs.get('m', None))

		vocs = self.e_cur_lesson_no
		if vocs not in self.vocs.keys():
		    self.vocs[vocs] = {'o': [], 't':[]}

	if self.e_voc_bool and name == 'o':
	    self.eo_voc_bool = 1

	if self.e_voc_bool and name == 't':
	    self.et_voc_bool = 1

    def endElement(self, name):
	if name == 'languages':
	    self.lang_bool = 0

	if self.lang_bool and name == 'o':
	    self.lang_o_bool = 0
	if self.lang_bool and name == 't': 
	    self.lang_t_bool = 0
	
	if name == 'lesson':
	    self.lesson_bool = 0
	
	if self.lesson_bool and name == 'desc':
	    self.desc_bool = 0
	
	if name == 'e':
	    self.e_voc_bool = 0
	
	if self.e_voc_bool and name == 'o':
	    self.eo_voc_bool = 0
	
	if self.e_voc_bool and name == 't':
	    self.et_voc_bool = 0
	    
    def characters(self, ch):
	if self.lang_o_bool:
	    self.lang_o = ch.encode('iso-8859-15')
	if self.lang_t_bool:
	    self.lang_t = ch.encode('iso-8859-15')
	
	if self.desc_bool:
	    self.vocabular[self.i-1] = { 'num' : int(self.lesson_no), 'inc' : self.lesson_inc,
	                                 'name': ch.encode('iso-8859-15'),
					 'o'   : [], 't' : [] }

	if self.eo_voc_bool: 
	    voc = ch.encode('iso-8859-15')
	    no = self.e_cur_lesson_no
	    self.vocs[no]['o'].append(voc)
	    
	if self.et_voc_bool:
	    voc = ch.encode('iso-8859-15')
	    no = self.e_cur_lesson_no
	    self.vocs[no]['t'].append(voc)

###############################################################################

if __name__ == '__main__':
    # Initialize the vt gui.
    gui = vt_gui()
    
    # Run the Gtk mainloop.
    gtk.mainloop()
