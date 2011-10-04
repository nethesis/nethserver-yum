#!/usr/bin/perl -w 

#----------------------------------------------------------------------
# copyright (C) 2004 Shad L. Lords <slords@mail.com>
# Copyright (C) 2005-2006 Gordon Rowell <gordonr@gormand.com.au>
# Copyright (C) 2005 Darrell May <dmay@myezserver.com>
# 
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
# 		
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# 		
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307  USA
#----------------------------------------------------------------------

package esmith::FormMagick::Panel::yum;

use strict;
use esmith::ConfigDB;
use esmith::FormMagick;
use CGI::FormMagick::TagMaker;
use esmith::util;
use esmith::cgi;
use File::Basename;
use File::stat;
use Exporter;
use Carp;

our @ISA = qw(esmith::FormMagick Exporter);

our @EXPORT = qw( print_yum_status_page );

our $VERSION = sprintf '%d.%03d', q$Revision: 1.0 $ =~ /: (\d+).(\d+)/;
our $db = esmith::ConfigDB->open or
	die "Couldn't open configuration database (permissions problems?)";

our %dbs;

for ( qw(available installed updates) )
{
    $dbs{$_} = esmith::ConfigDB->open_ro("yum_$_") or
	die "Couldn't open yum_$_ DB\n";
}

for ( qw(repositories) )
{
    $dbs{$_} = esmith::ConfigDB->open("yum_$_") or
	die "Couldn't open yum_$_ DB\n";
}

=pod 

=head1 NAME

esmith::FormMagick::Panels::yum - useful panel functions

=head1 SYNOPSIS

use esmith::FormMagick::Panels::yum;

my $panel = esmith::FormMagick::Panel::yum->new();
$panel->display();

=head1 DESCRIPTION

=cut

=head2 new();

Exactly as for esmith::FormMagick

=cut

sub new {
    shift;
    my $self = esmith::FormMagick->new();
    $self->{calling_package} = (caller)[0];
    bless $self;
    return $self;
}

=head2 get_prop ITEM PROP

A simple accessor for esmith::ConfigDB::Record::prop

=cut

sub get_prop {
    my ($fm, $item, $prop, $default) = @_;
    warn "You must specify a record key"    unless $item;
    warn "You must specify a property name" unless $prop;
    my $record = $db->get($item) or warn "Couldn't get record for $item";
    my $value = $record ? $record->prop($prop) : undef;
    return defined $value ? $value : $default;
}

=head2 get_value ITEM

A simple accessor for esmith::ConfigDB::Record::value

=cut

sub get_value {
    my ($fm,$item,$default) = @_;
    my $record = $db->get($item) or warn "Couldn't get record for $item";
    my $value = $record ? $record->value() : undef;
    return defined $value ? $value : $default;
}

sub is_empty
{
    my ($fm, $yumdb) = @_;

    my $groups = $dbs{$yumdb}->get_all_by_prop(type => 'group') || 'none';
    my $packages = $dbs{$yumdb}->get_all_by_prop(type => 'package') || 'none';

    #Show no updates if both = none
    return 1 if ($packages eq $groups);

    #else return here
    return;
}

sub non_empty
{
    my ($fm, $yumdb, $type) = @_;

    $type ||= 'both';

    return 0 unless (exists $dbs{$yumdb});

    my $groups   = scalar $dbs{$yumdb}->get_all_by_prop(type => 'group');
    return $groups if ($type eq 'group');

    my $packages = scalar $dbs{$yumdb}->get_all_by_prop(type => 'package');
    if ($type eq 'package')
    {
	return $fm->package_functions_enabled ? $packages : 0;
    }

    return ($fm->package_functions_enabled or $yumdb eq 'updates') ?
		($groups || $packages) : $groups;
}

sub get_options
{
    my ($fm, $yumdb, $type) = @_;

    my %options;

    for ($dbs{$yumdb}->get_all_by_prop(type => $type))
    {
        $options{$_->key} = $_->key . " " . $_->prop("Version") . " - " .
            $_->prop("Repo");
    }

    return \%options;
}

sub get_names
{
    return [ keys %{get_options(@_)} ];
}

sub get_avail
{
    my ($fm, $yumdb, $type) = @_;

    return $fm->get_options("available", "package");
}

sub get_status
{
    my ($fm, $prop, $localise) = @_;

    my $status = $db->get_prop("yum", $prop) || 'disabled';

    return $status unless $localise;

    return $fm->localise($status eq 'enabled' ? 'ENABLED' : 'DISABLED');
}

sub change_settings
{
    my ($fm) = @_;
    my $q = $fm->{'cgi'};

    for my $param ( qw(
			PackageFunctions
            	) )
    {
	$db->set_prop('yum', $param, $q->param("yum_$param"));
    }

    my $check4updates = $q->param("yum_check4updates");
    my $status = 'disabled';

    if ($check4updates ne 'disabled') { $status = 'enabled'; }

    $db->set_prop('yum', 'check4updates', $check4updates);
    $db->set_prop('yum', 'status', $status);

    my %selected = map {$_ => 1} $q->param('SelectedRepositories');

    foreach my $repos (
	$dbs{repositories}->get_all_by_prop(type => "repository") )
    {
	$repos->set_prop("status", 
		exists $selected{$repos->key} ? 'enabled' : 'disabled');

    }

    $dbs{repositories}->reload;

    unless ( system( "/sbin/e-smith/signal-event", "yum-modify" ) == 0 )
    {
	$fm->error('ERROR_UPDATING_CONFIGURATION');
	return undef;
    }

    $fm->success('SUCCESS');
}

sub do_yum
{
    my ($fm, $function) = @_;
    my $q = $fm->{'cgi'};

    for ( qw(SelectedGroups SelectedPackages) )
    {
	$db->set_prop("yum", $_, join(',', ($q->param($_) )));
    }

    esmith::util::backgroundCommand(0,
        "/sbin/e-smith/signal-event", "yum-$function");

    $db->reload;

    $fm->print_yum_status_page();
}

sub print_skip_header
{
    return "<INPUT TYPE=\"hidden\" NAME=\"skip_header\" VALUE=\"1\">\n";
}

sub format_yum_log
{
    my ($fm) = @_;

    my $yum_log = $db->get_prop('yum', 'LogFile');

    return '' unless $yum_log and -f $yum_log;

    my @contents;

    open my $log_file, "<", $yum_log or die "Couldn't open $yum_log\n";
    push @contents, "<PRE>", <$log_file>, "</PRE>";
    close $log_file or die "Failed to close $yum_log\n";

    return @contents;
}

sub print_yum_status_page
{
    my ($fm) = @_;
    my @yum_status;

    open(YUM_STATUS, "</var/run/yum.status");
    @yum_status = <YUM_STATUS>;
    close(YUM_STATUS);

    my @yum_log = $fm->format_yum_log();

    my $page_output = << "EOF";
Expires: 0
Refresh: 10; URL=/server-manager/cgi-bin/yum
Content-type: text/html

<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML//EN">

<HTML>
<HEAD>
  <META HTTP-EQUIV="refresh" CONTENT="10;URL=/server-manager/cgi-bin/yum">
  <TITLE>server manager</TITLE>
  <LINK REL="stylesheet" TYPE="text/css"
  HREF="/server-common/css/sme_core.css">
  </HEAD>
  <BODY TOPMARGIN="0" LEFTMARGIN="0" MARGINHEIGHT="0" MARGINWIDTH="0">
  <BR><center><H2>Please Wait - Yum Running (@yum_status)</H2></center>
  @yum_log
  </BODY>
</HTML>
EOF

    print $page_output;
}

sub package_functions_enabled
{
    my ($fm) = @_;

    return ($db->get_prop("yum", "PackageFunctions") eq "enabled");
}

sub get_repository_options
{
    my $self = shift;

    my %options;

    foreach my $repos (
	$dbs{repositories}->get_all_by_prop(type => "repository") )
    {
	next unless ($repos->prop('Visible') eq 'yes'
		or $repos->prop('status') eq 'enabled');

        $options{$repos->key} = $repos->prop('Name');
    }

    return \%options;
}

sub get_repository_current_options
{
    my $self = shift;

    my @selected;

    foreach my $repos ( 
	$dbs{repositories}->get_all_by_prop( type => "repository" ) )
    {
	next unless ($repos->prop('Visible') eq 'yes'
		or $repos->prop('status') eq 'enabled');

        push @selected, $repos->key if ($repos->prop('status') eq 'enabled');
    }

    return \@selected;
}

sub post_upgrade_reboot
{
    my $fm = shift;

    $db->get_prop_and_delete('yum', 'LogFile');

    $db->reload;

    if (fork == 0)
    {
        exec "/sbin/e-smith/signal-event post-upgrade; /sbin/e-smith/signal-event reboot";
        die "Exec failed";
    }

    $fm->success('SYSTEM_BEING_RECONFIGURED');
}

sub display_yum_log
{
    my $fm = shift;

    print $fm->format_yum_log();

    my $yum_log = $db->get_prop_and_delete('yum', 'LogFile');
    return;
}

sub skip_to_postupgrade
{
    my $fm = shift;

    $fm->success('HEADER_POSTUPGRADE_REQUIRED', 'YUM_PAGE_POSTUPGRADE');

    $fm->{cgi}->param(-name => "page",       -value => 0);
    $fm->{cgi}->param(-name => "page_stack", -value => '');
    $fm->{cgi}->param(-name => "Next",       -value => 'Next');
    $fm->{cgi}->param(-name => "wherenext",  -value =>'YUM_PAGE_POSTUPGRADE');
}

1;
