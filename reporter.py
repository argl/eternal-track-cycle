#!/usr/bin/env python

import os
import glob
import re
import time
import sets
import httplib
from urlparse import urlparse
from urllib import quote

conf = {
  "stats_dir": "c:\\Program Files\\rFactor\\UserData\\LOG\\Results\\", 
  "interval": 10.0,
  "post_url": "http://YOURHOST.com/report.php",
  "season_name": "SEASON NAME"
  }

def post_file(url, filename):
  if(os.path.exists(filename)):
    f = open(filename, 'rb')
    conn = httplib.HTTPConnection(url.netloc)
    headers = {"Content-type": "text/xml"}
    conn.request("POST", url.path+"?season="+quote(conf["season_name"]), f.read(), headers)
    f.close()
    response = conn.getresponse()
    print response.read()
  
def main():
  url = urlparse(conf["post_url"])
  existing_files = sets.Set(glob.glob(conf["stats_dir"] + "*SR.xml"))
  print existing_files
  while(True):
    files = sets.Set(glob.glob(conf["stats_dir"] + "*SR.xml"))
    print "."
    new_files = files.difference(existing_files)
    #print new_files
    if(len(new_files) > 0):
      for f in new_files:
        print f
        post_file(url, f)
    existing_files = files
    time.sleep(conf["interval"])
  

if __name__ == "__main__":
  main()