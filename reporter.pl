#!/usr/bin/env perl

use LWP::UserAgent;
use HTTP::Request::Common;
use URI::Escape ('uri_escape');

#$stats_dir = "c:\\Program Files\\rFactor\\UserData\\LOG\\Results\\"; 
$stats_dir = "/tmp/"; 
$interval = 10.0;
#$post_url = "http://YOURHOST.com/report.php";
$post_url = "http://localhost/report.php";
$season_name = "SEASON NAME";

sub post_file {
  $filename = $_[0];
  open(F, $filename) || return;
  @raw_data=<F>;
  close(F);
  print "$post_url?season=".uri_escape($season_name)."\n";
  $req = HTTP::Request->new(POST => $post_url."?season=".uri_escape($season_name));
  $req->content(join("", @raw_data));
  $ua = LWP::UserAgent->new;
  $res = $ua->request($req);
  if ($res->is_success) {
    print $res->content;
  } else {
    print $res->status_line, "\n";
    print $res;
  }
}


@existing_files = <$stats_dir*SR.xml>;
print "existing files:\n";
print join("\n", @existing_files);
print "\n";

while(true) {
  @new_files = <$stats_dir*SR.xml>;
  %count = ();
  @intersection = @difference = ();
  foreach $element (@existing_files, @new_files) { $count{$element}++ }
  foreach $element (keys %count) {
    push @{ $count{$element} > 1 ? \@intersection : \@difference }, $element;
  }
  if (scalar(@difference) > 0) {
    foreach $file (@difference) {
      print $file . "\n";
      post_file($file);
    }
  }
  print ".\n";
  @existing_files = @new_files;
  sleep $interval;
}