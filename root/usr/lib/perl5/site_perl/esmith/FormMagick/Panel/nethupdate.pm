#!/usr/bin/perl -w 

package esmith::FormMagick::Panel::nethupdate;

use strict;
use esmith::ConfigDB;
use esmith::FormMagick;
use CGI::FormMagick::TagMaker;
use esmith::util;
use esmith::cgi;
# use esmith::rpm::installed;
use RPM2;
use File::Basename;
use File::stat;
use Exporter;
use Carp;
use LWP::Simple;
use HTTP::Request::Common;
use HTML::Tabulate;


our @ISA = qw(esmith::FormMagick Exporter);

our @EXPORT = qw( print_yum_status_page );

our $VERSION = sprintf '%d.%03d', q$Revision: 1.0 $ =~ /: (\d+).(\d+)/;
our $db = esmith::ConfigDB->open or
	die "Couldn't open configuration database (permissions problems?)";

our $rpm_db = RPM2->open_rpm_db();

our %_nethextra_available;
our %_nethextra_installed;
our $_nethextra_success = 0;
our $_nethextra_initialized = 0;

sub new {
    shift;
    my $self = esmith::FormMagick->new();
    $self->{calling_package} = (caller)[0];
    bless $self;
    return $self;
}

sub initialize_nethextra
{
    my ($self) = @_;
    my (@serv_out);

    if($_nethextra_initialized == 1) { return $_nethextra_success; }
    $_nethextra_initialized = 1;

    @serv_out = split(/\n/, get(
        "http://update.nethesis.it/nethpackages.php" .
        "?LK=" . get_nethupdate_systemid() . "&R=" . get_nethrelease()));
#    @serv_out = split(/\n/, `/bin/cat /tmp/nethextra_packages.txt`);

    if(scalar @serv_out < 1)
    {
        $self->error("NETHEXTRA_DOWNLOAD_LIST_ERROR");
        $_nethextra_success = 0;
        return $_nethextra_success;
    }

    my $ret_code = shift(@serv_out);

    if(not ($ret_code eq "###RESULT###OK###RESULT###"))
    {
        $self->error("NETHEXTRA_DOWNLOAD_LIST_REFUSED");
        $_nethextra_success = 0;
        return $_nethextra_success;
    }

    foreach (@serv_out)
    {
        my @serv_line  = split(/:/);

        if(scalar @serv_line != 3)
        {
            $self->error("NETHEXTRA_LIST_WRONG_FORMAT");
            $_nethextra_success = 0;
            return $_nethextra_success;
        }

        my $rpms = $rpm_db->find_by_name_iter($serv_line[1]);

        while (my $pkg = $rpms->next)
        {
            if($pkg->tag("name") eq $serv_line[1])
            {
                $_nethextra_installed{$serv_line[1]}{'name'} = $serv_line[1];
                $_nethextra_installed{$serv_line[1]}{'version'} = $pkg->tag("version")."-".$pkg->tag("release");
                $_nethextra_installed{$serv_line[1]}{'description'} = $serv_line[2];
            }
        }
        if(not defined $_nethextra_installed{$serv_line[1]})
        {
            $_nethextra_available{$serv_line[1]}{'name'} = $serv_line[1];
            $_nethextra_available{$serv_line[1]}{'description'} = $serv_line[2];
        }
    }

    $_nethextra_success = 1;
    return $_nethextra_success;
}

sub get_nethextra_packages
{
    my ($self, $type) = @_;
    my ($list_ref, %pkg_list);

    if(not initialize_nethextra($self)) { return undef; }

    if($type eq 'available') { $list_ref = \%_nethextra_available; }
    if($type eq 'installed') { $list_ref = \%_nethextra_installed; }

    while (my ($key, $value) = each(%{$list_ref}))
    {
        $pkg_list{$key} = $value->{'description'};
    }

    return \%pkg_list;
}

sub is_nethextra_available
{
    my ($self) = @_;
    if(not initialize_nethextra($self)) { return 0 };
    if(scalar keys %_nethextra_available > 0) { return 1; }
    return 0;
}

sub is_nethextra_not_available
{
    return not is_nethextra_available();
}

sub print_installed_nethextra
{
    my $self = shift;
    my $q = $self->{cgi};

    my $nethextra_table =
    {
        title => $self->localise('CURRENT_INSTALLED_NETHEXTRA'),
        stripe => '#D4D0C8',
        fields => [ qw(Package Version Description) ],
        labels => 1
    };

    my @data = ();

    if(not initialize_nethextra($self)) { return undef; }
    if(scalar keys %_nethextra_installed < 1) { return undef; }

    while ( my ($key, $value) = each(%_nethextra_installed) )
    {
        push @data, {
            Package => $key,
            Version => $value->{'version'},
            Description => $value->{'description'}
        }
    }

    my $t = HTML::Tabulate->new($nethextra_table);

    return $t->render(\@data, $nethextra_table);
}

sub register_nethupdate_systemid
{
    my ($fm, $function) = @_;
    my $NethSystemID = $fm->{cgi}->param('NethSystemID');
    $NethSystemID =~ tr/a-z/A-Z/;

    my @reg_out = split(/###RESULT###/,
        get("http://register.nethesis.it/server/register.php?license=".$NethSystemID));

    if(not defined $reg_out[1])
    {
        $fm->error('NETHUPDATE_REGISER_KEY_FAILED', 'NETHUPDATE_INSERT_SYSTEMID');
        return undef;
    }

    if(not $reg_out[1] eq "OK")
    {
        $fm->error($reg_out[1], 'NETHUPDATE_INSERT_SYSTEMID');
        return undef;
    }

    $db->set_prop('nethupdate', 'SystemID', $NethSystemID);

    unless(fork())
    {
        close(STDIN); close(STDERR); close(STDOUT);
        system( "/sbin/e-smith/signal-event", "nethupdate-install-systemid" );
        exit 0;
    }

    $fm->success('SYSTEMID_KEY_REGISTRATION_SUCCESS', 'First');
}

sub get_nethupdate_systemid { return $db->get_prop('nethupdate','SystemID'); }

sub get_nethrelease { return $db->get_prop('sysconfig', 'ReleaseVersion'); }

sub is_nethupdate_systemid_present
{
    if($db->get_prop('nethupdate','SystemID')) { return 1; }
    return 0;
}

sub is_nethupdate_systemid_not_present
{
    return not is_nethupdate_systemid_present();
}

sub validate_nethupdate_systemid
{
    my ($self, $first) = @_;

    my $NethSystemID = $self->{cgi}->param('NethSystemID');
    $NethSystemID =~ tr/a-z/A-Z/;

    if($NethSystemID =~ /^[0-9A-Z]{8}(-[0-9A-Z]{4}){8}$/) { return "OK"; }

    return "NETHUPDATE_SYSTEMID_WRONG_SYNTAX";
}

sub nethupdate_systemid_and_packages_available
{
    if(is_systemid_present() and is_nethextra_available()) { return 1; }
    return 0;
}

sub print_install_nethextra_button
{
    my ($fm) = @_;
    if(is_nethextra_available())
    {
        $fm->print_button('NETHEXTRA_INSTALL_PACKAGES');
    }
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

sub get_prop {
    my ($fm, $item, $prop, $default) = @_;
    warn "You must specify a record key"    unless $item;
    warn "You must specify a property name" unless $prop;
    my $record = $db->get($item) or warn "Couldn't get record for $item";
    my $value = $record ? $record->prop($prop) : undef;
    return defined $value ? $value : $default;
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
Refresh: 10; URL=/server-manager/cgi-bin/nethupdate
Content-type: text/html

<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML//EN">

<HTML>
<HEAD>
  <META HTTP-EQUIV="refresh" CONTENT="10;URL=/server-manager/cgi-bin/nethupdate">
  <TITLE>server manager</TITLE>
  <LINK REL="stylesheet" TYPE="text/css"
  HREF="/server-common/css/sme_core.css">
  </HEAD>
  <BODY TOPMARGIN="0" LEFTMARGIN="0" MARGINHEIGHT="0" MARGINWIDTH="0">
  <BR><center><H2>Attendere Prego - aggiornamento in corso (@yum_status)</H2></center>
  @yum_log
  </BODY>
</HTML>
EOF

    print $page_output;
}

sub get_prop {
    my ($fm, $item, $prop, $default) = @_;
    warn "You must specify a record key"    unless $item;
    warn "You must specify a property name" unless $prop;
    my $record = $db->get($item) or warn "Couldn't get record for $item";
    my $value = $record ? $record->prop($prop) : undef;
    return defined $value ? $value : $default;
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

    $fm->success('INSTALL_NETHEXTRA_LOG', 'YUM_PAGE_POSTUPGRADE');

    $fm->{cgi}->param(-name => "page",       -value => 0);
    $fm->{cgi}->param(-name => "page_stack", -value => '');
    $fm->{cgi}->param(-name => "Next",       -value => 'Next');
    $fm->{cgi}->param(-name => "wherenext",  -value => 'YUM_PAGE_POSTUPGRADE');
}

1;
