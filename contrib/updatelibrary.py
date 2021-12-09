#!/usr/bin/python

# updatelibrary.py Version 1.2 by Rob Knop aka Prospero Frobozz (rknop@pobox.com)

# Written with some reference to genassets.pl by Illuminous Beltran/IBM,
# as found on the OpenSim wiki.

# Copyright 2011 Rob Knop (aka Prospero Frobozz). All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are
# met:
#
#    1. Redistributions of source code must retain the above copyright
#       notice, this list of conditions and the following disclaimer.

#    2. Redistributions in binary form must reproduce the above
#       copyright notice, this list of conditions and the following
#       disclaimer in the documentation and/or other materials provided
#       with the distribution.
#
# THIS SOFTWARE IS PROVIDED BY ROB KNOP ``AS IS'' AND ANY
# EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
# IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
# PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL ROB KNOP OR
# CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
# EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
# PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
# PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
# LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
# NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
# SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
#
# The views and conclusions contained in the software and documentation
# are those of the authors and should not be interpreted as representing
# official policies, either expressed or implied, of Rob Knop.


import os, os.path, sys, stat, shutil, re, uuid
import xml.dom, xml.dom.minidom
import traceback
from optparse import OptionParser


opensim_library_root_uuid = "00000112-000f-0000-0000-000100bba000"

# **********************************************************************

class AssetExistsException(Exception):
    def __init__(self, value):
        self.value = value
    def __str__(self, value):
        return repr(value)

class InventoryException(Exception):
    def __init__(self, value):
        self.value = value
    def __str__(self, value):
        return repr(value)

class NiniException(Exception):
    def __init__(self, value):
        self.value = value
    def __str__(self, value):
        return repr(value)

# **********************************************************************
class NiniSection(object):
    def __init__(self, sectionname, keyvalues):
        self.sectionname = sectionname
        self.keyvalues = keyvalues

    def name(self):
        return self.sectionname

    def keyvalues(self):
        return self.keyvalues

    def value(self, key):
        return self.keyvalues[key]

    def setvalue(self, key, value):
        self.keyvalues[key] = value

# **********************************************************************

class NiniThing(object):
    def __init__(self, keynames, ninitype):
        self.keynames = keynames
        self.ninitype = ninitype

    def readXML(self, filename):
        sections = []

        if not os.path.exists(filename):
            sys.stderr.write("WARNING: {0} file {1} doesn't exist. Returning empty list.\n".
                             format(self.ninitype, filename))
            return sections

        xmlblob = xml.dom.minidom.parse(filename)
        topelem = xmlblob.documentElement
        if topelem.tagName != "Nini":
            raise NiniException("Error not a Nini file: {0}".format(filename))

        for sec in topelem.childNodes:
            if sec.nodeType == xml.dom.Node.ELEMENT_NODE:
                if sec.tagName != 'Section':
                    raise NiniException("Error {0} file: expecting a 'Section', found a {1}".
                                        format(self.ninitype), sec.tagName)

                item = NiniSection(sec.getAttribute("Name"), dict())
                for keyname in self.keynames:
                    item.setvalue(keyname, "")

                for seckey in sec.childNodes:
                    if seckey.nodeType == xml.dom.Node.ELEMENT_NODE:
                        if seckey.tagName != "Key":
                            raise NiniException("Error reading {0}: expecting a 'Key', found a {1}".
                                                format(self.ninitype, seckey.tagName) )
                        item.setvalue(seckey.getAttribute("Name"), seckey.getAttribute("Value"))

                sections.append(item)

        xmlblob.unlink()

        return sections

    def writeXML(self, filename, sections):
        ofh = open(filename, "w")

        ofh.write("<Nini>\n")

        for item in sections:
            ofh.write("  <Section Name=\"{0}\">\n".format(item.name()))
            for keyname in self.keynames:
                ofh.write("    <Key Name=\"{0}\" Value=\"{1}\" />\n".format(keyname, item.value(keyname)))
            ofh.write("  </Section>\n\n")

        ofh.write("</Nini>\n")
        ofh.close()


# **********************************************************************

class AssetSet(NiniThing):
    def __init__(self):
        super(AssetSet,self).__init__( [ "assetID",
                                          "name",
                                          "assetType",
                                          "inventoryType",
                                          "fileName"
                                          ],
                                        "Asset Set" )
        self.assets = []

    def __iter__(self):
        return self.assets.__iter__()

    def addasset(self, uuid, name, asstype, invtype, filename):
        ass = NiniSection(filename,
                          { 'assetID': uuid,
                            'name': name,
                            'assetType': asstype,
                            'inventoryType': invtype,
                            'fileName': filename }
                          )
        self.assets.append(ass)
        return ass

    def findbyfilename(self, filename):
        for ass in self.assets:
            if ass.value('fileName') == filename:
                return ass
        return None

    def findbyuuid(self, uuid):
        for ass in self.assets:
            if ass.value('assetID') == uuid:
                return ass
        return None

    def readXML(self, filename):
        self.assets = super(AssetSet, self).readXML(filename)

    def writeXML(self, filename):
        super(AssetSet, self).writeXML(filename, self.assets)

# **********************************************************************

class InvFolders(NiniThing):
    def __init__(self):
        super(InvFolders, self).__init__( [ "folderID",
                                            "parentFolderID",
                                            "name",
                                            "type"
                                            ],
                                          "Inventory Folders" )
        self.folders = []

    def __iter__(self):
        return self.folders.__iter__()

    def findbyuuid(self, uuid):
        for folder in self.folders:
            if folder.value('folderID') == uuid:
                return folder
        return None

    # NOTE -- findbyname only finds the first folder of
    #  the given name inside the given parent folder.
    #  It is possible, if perverse, to have more than
    #  one folder with the same name...

    def findbyname(self, name, parentid):
        for folder in self.folders:
            if ( ( folder.value('parentFolderID') == parentid ) and
                 ( folder.value('name') == name ) ):
                return folder
        return None

    def ensureexists(self, path, name, parentid):
        if ( ( parentid != opensim_library_root_uuid ) and
             ( not self.findbyuuid(parentid) ) ):
            raise InventoryException("No such parent folder exists: " + str(parentid))

        folder = self.findbyname(name, parentid)
        if folder != None:
            return folder

        # HACK ALERT -- I want this "type 6" to become whatever the right
        #  type is for no icon
        # http://opensimulator.org/wiki/AssetServer/DeveloperDocs doesn't tell me enough

        folder = NiniSection(path,
                             { 'folderID' : uuid.uuid4(),
                               'parentFolderID' : parentid,
                               'name' : name,
                               'type' : -1
                               }
                             )
        self.folders.append(folder)

        return folder


    def readXML(self, filename):
        self.folders = super(InvFolders, self).readXML(filename)

    def writeXML(self, filename):
        super(InvFolders, self).writeXML(filename, self.folders)



# **********************************************************************

class InvItems(NiniThing):
    def __init__(self, folders):
        super(InvItems, self).__init__( [ "inventoryID",
                                          "assetID",
                                          "folderID",
                                          "name",
                                          "description",
                                          "assetType",
                                          "inventoryType",
                                          "currentPermissions",
                                          "nextPermissions",
                                          "everyonePermissions",
                                          "basePermissions"
                                          ],
                                        "Inventory Items" )
        self.folders = folders
        self.items = []

    def findbyinvid(self, uuid):
        for item in self.items:
            if item.value('inventoryID') == uuid:
                return item
        return None

    # Note -- findbyname only finds the first one of that name
    #  in that folder.  You can have more than one thing of
    #  the same name in a given folder....

    def findbyname(self, name, folderid):
        for item in self.items:
            if ( ( item.value('name') == name ) and
                 ( item.value('folderID') == folderid) ):
               return item
        return None

    def ensureexists(self, path, name, assetid, assettype, invtype, folderid):
        if ( ( folderid != opensim_library_root_uuid) and
             ( not self.folders.findbyuuid(folderid) ) ):
            raise InventoryException("No such folder for item: " + str(folderid))

        for item in self.items:
            if ( ( item.value('assetID') == assetid ) and
                 ( item.value('folderID') == folderid ) and
                 ( item.value('name') == name ) ):
                return item

        item = NiniSection(path,
                           { 'inventoryID' : uuid.uuid4(),
                             'assetID' : assetid,
                             'folderID' : folderid,
                             'name' : name,
                             'description' : "",
                             'assetType' : assettype,
                             'inventoryType' : invtype,
                             'currentPermissions' : "2147483647",
                             'nextPermissions' : "2147483647",
                             'everyonePermissions' : "2147483647",
                             'basePermissions' : "2147483647"
                             }
                           )
        self.items.append(item)

        return item


    def readXML(self, filename):
        self.items = super(InvItems, self).readXML(filename)

    def writeXML(self, filename):
        super(InvItems, self).writeXML(filename, self.items)


# **********************************************************************

class LibraryGenerator(object):
    def __init__(self, libname, shortname):
        self.libname = libname
        self.shortname = shortname
        self.asset_dir = os.path.abspath(os.path.join("assets", "{0}AssetSet".format(shortname)))
        self.inv_dir = os.path.abspath(os.path.join("inventory", shortname))
        self.asset_xmlfile = "{0}AssetSet.xml".format(shortname)
        self.folders_xmlfile = "{0}InvFolders.xml".format(shortname)
        self.items_xmlfile = "{0}InvItems.xml".format(shortname)
        self.skips = []
        self.wipeinv = False

        self.matchj2k = re.compile("\.j2k$")
        self.matchtxt = re.compile("\.txt$")
        self.matchlsl = re.compile("\.lsl$")
        self.endsinslash = re.compile("/$")
        self.commentline = re.compile("^\s*#")
        self.blankline = re.compile("^\s*$")
        self.assetlistline = re.compile("^\s*([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})\s*(\d+)\s*(\d+)\s*(.*\S)\s*$")

    def set_skip_patterns(self, skips):
        self.skips = skips

    def set_asset_dir(self, asset_dir):
        self.asset_dir = asset_dir

    def set_inv_dir(self, inv_dir):
        self.inv_dir = inv_dir

    def set_asset_xmlfile(self, asset_xmlfile):
        self.asset_xmlfile = asset_xmlfile

    def set_folders_xmlfile(self, folders_xmlfile):
        self.folders_xmlfile = folders_xmlfile

    def set_items_xmlfile(self, items_xmlfile):
        self.items_xmlfile = items_xmlfile

    def wipe_inventory(self):
        self.wipeinv = True
        

    def run(self):
        # Create our storage for things, and read any current
        #  XML files if they exist

        self.assets = AssetSet()
        self.assets.readXML(os.path.join(self.asset_dir, self.asset_xmlfile))

        self.folders = InvFolders()
        if not self.wipeinv:
            self.folders.readXML(os.path.join(self.inv_dir, self.folders_xmlfile))

        self.items = InvItems(self.folders)
        if not self.wipeinv:
            self.items.readXML(os.path.join(self.inv_dir, self.items_xmlfile))


        # Run the recursive directory processor thingie

        origdir = os.getcwd()
        os.chdir(self.asset_dir)
        self.process_dir(opensim_library_root_uuid, "", "")
        os.chdir(origdir)

        # Write the XML files, saving copies of the old ones out of paranoia

        if os.path.exists(os.path.join(self.asset_dir, self.asset_xmlfile)):
            shutil.copy2(os.path.join(self.asset_dir, self.asset_xmlfile),
                         os.path.join(self.asset_dir, "{0}-old".format(self.asset_xmlfile)))

        self.assets.writeXML(os.path.join(self.asset_dir, self.asset_xmlfile))

        if os.path.exists(os.path.join(self.inv_dir, self.folders_xmlfile)):
            shutil.copy2(os.path.join(self.inv_dir, self.folders_xmlfile),
                         os.path.join(self.inv_dir, "{0}-old".format(self.folders_xmlfile)))

        self.folders.writeXML(os.path.join(self.inv_dir, self.folders_xmlfile))

        if os.path.exists(os.path.join(self.inv_dir, self.items_xmlfile)):
            shutil.copy2(os.path.join(self.inv_dir, self.items_xmlfile),
                         os.path.join(self.inv_dir, "{0}-old".format(self.items_xmlfile)))

        self.items.writeXML(os.path.join(self.inv_dir, self.items_xmlfile))


    def process_dir(self, parent_uuid, my_name, path):
        origdir = os.getcwd()
        sys.stderr.write("Processing directory: {0} \n".format(os.path.join(self.asset_dir, path)))
        if my_name != "":
            os.chdir(my_name)
        else:
            my_name = self.libname
            
        # Create the folder XML blob if necessary

        if (path == ""):
            curfolder = self.folders.ensureexists(self.libname, my_name, parent_uuid)
        else:
            curfolder = self.folders.ensureexists(path, my_name, parent_uuid)
        my_uuid = curfolder.value("folderID")

        # Walk through this directory and process everything in it

        for fname in os.listdir("."):
            statinfo = os.stat(fname)

            doskip = False
            for skip in self.skips:
                if re.search(skip, fname):
                    doskip = True
            if doskip: continue

            if stat.S_ISDIR(statinfo.st_mode):
                self.process_dir(my_uuid, fname, os.path.join(path, fname) )
                continue

            fpath = os.path.join(path, fname)
            
            if fpath == self.asset_xmlfile:
                # Ignore the XML file we're working on!
                continue

            elif fname == "addassets.lis":
                # Ignore the file specifing pre-existing assets to add
                #   to inventory
                continue

            elif self.matchj2k.search(fname):
                asset_type = 0
            elif self.matchtxt.search(fname):
                asset_type = 7
            elif self.matchlsl.search(fname):
                asset_type = 10
            else:
                sys.stderr.write("WARNING: Skipping unrecognized file " +
                                 fname + "\n")
                continue

            # Strip the extension for aesthetic purposes
            #  (Right now, that must be .txt, .lsl, or .j2k, so
            #   we can assume it's 4 characters long.)

            name = fname[0:-4]

            # Create the asset XML blob if necessary

            ass = self.assets.findbyfilename(fpath)
            if ass == None:
                ass = self.assets.addasset(uuid.uuid4(), name, asset_type,
                                           asset_type, os.path.join(path, fname))

            # Create the inventory XML blob if necessary

            self.items.ensureexists(os.path.join(path, name), name, ass.value('assetID'), 
                                    asset_type, asset_type, my_uuid)

        # Add pre-existing assets (to inventory only) from file addassets.lis

        if os.path.exists("addassets.lis"):
            asslis = open("addassets.lis")
            for line in asslis:
                line.rstrip("\r\n")
                if self.blankline.search(line) or self.commentline.search(line):
                    continue
                match = self.assetlistline.search(line)
                if match == None:
                    sys.stderr.write("WARNING : error parsing line in {0}\n".
                                     format(os.path.join(path, "addassets.lis")))
                    sys.stderr.write("Offending line: \"{0}\"\n".format(line))
                    continue
                self.items.ensureexists(os.path.join(path, match.group(4)), match.group(4), 
                                        match.group(1), match.group(2), match.group(3), my_uuid)
            asslis.close()

        # We're done; restore the CWD to where we started and return

        os.chdir(origdir)

# **********************************************************************

def main():

    usage = """
    updatelibrary.py -n "Library Name" -s ShortLibName [options]

  Goes through your asset set directory.  (See below for how to specify
  it, and what the default is.)  Finds all .j2k images, .txt files, and
  .lsl files in that directory and all subdirectories.  Generates an
  Asset Set XML file for all of those files.  Also generates library
  Inventory Folder and Inventory Item XML files for all of those assets,
  organized in a folder hierarchy that matches the directory hierarchy.

  Additional assets that already exist in the grid's asset store may be
  specified in each directory in a file named "addassets.lis".

  For more documentation, see http://opensimulator.org/wiki/Custom_Libraries

  When done, you will need to manually add your asset directory to the
  OpenSim AssetSets.xml file (in "assets" under the OpenSim bin
  directory), and your inventory XML files to the Libraries.xml file (in
  "inventory" under the OpenSim bin directory).

  Written 2011 by Rob Knop aka Prospero Frobozz.  I've only used this on
  Linux; I have no idea how well it will function on Windows.  If you're
  on Windows, I recommend http://www.ubuntu.com

  Run updatelibrary.py --help for option listings
"""

    parser = OptionParser(usage = usage)

    parser.add_option("-n", "--lib-name", action="store", type="string",
                      dest="libname", default="New Library",
                      help="The name of your library as it appears in the viewer")

    parser.add_option("-s", "--short-name", action="store", type="string",
                      dest="shortname", default="NewLibrary",
                      help="A name without spaces of your library for filename purposes.")

    parser.add_option("-w", "--wipe-inventory", action="store_true", default=False,
                      dest="wipeinv",
                      help="Wipe & start over the inventory? (Safe! Use this if you've removed things.)")

    parser.add_option("-a", "--asset-dir", action="store",
                      type="string", dest="asset_dir",
                      default=None,
                      help="Directory where your .j2k, .txt, and .lsl files are, and which will "+
                      "serve as the Assset Set in the OpenSim library.  Defaults to assets/[shortname]AssetSet")

    parser.add_option("-i", "--inv-dir", action="store", type="string",
                      dest="inv_dir", default=None,
                      help="Directory in which to put the inventory XML files.  Defaults to "+
                      "inventory/[shortname]")

    parser.add_option("", "--asset-xmlfile", action="store",
                      type="string", dest="asset_xmlfile", default=None,
                      help="Name of Asset XML file.  Defaults to [shortname]AssetSet.xml")

    parser.add_option("", "--folders-xmlfile", action="store",
                      type="string", dest="folders_xmlfile", default=None,
                      help="Name of Inventory Folders XML file.  Defaults to [shortname]InvFolders.xml")

    parser.add_option("", "--items-xmlfile", action="store",
                      type="string", dest="items_xmlfile", default=None,
                      help="Name of Inventory Folders XML file.  Defaults to [shortname]InvItems.xml")

    parser.add_option("", "--skip", action="append", type="string",
                      dest="skip_patterns",
                      default=['^\.hg$', '^\.git$', '^\.svn$'],
                      help="Regex for filenames to not process")

    if len(sys.argv) < 2:
        parser.print_usage()
        sys.exit(0)

    options, args = parser.parse_args()

    try:
        generator = LibraryGenerator(options.libname, options.shortname)

        if options.skip_patterns:
            generator.set_skip_patterns(options.skip_patterns)

        if options.asset_dir != None:
            generator.set_asset_dir(options.asset_dir)

        if options.inv_dir != None:
            generator.set_inv_dir(options.inv_dir)

        if options.asset_xmlfile != None:
            generator.set_asset_xmlfile(options.asset_xmlfile)

        if options.folders_xmlfile != None:
            generator.set_folders_xmlfile(options.folders_xmlfile)

        if options.items_xmlfile != None:
            generator.set_items_xmlfile(options.items_xmlfile)

        if options.wipeinv:
            generator.wipe_inventory()

        generator.run()

    except Exception:
        print "FAIL"
        traceback.print_exc()
        sys.exit(20)


# **********************************************************************

if __name__ == "__main__":
    sys.exit(main())
