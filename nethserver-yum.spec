Name: nethserver-yum
Summary: NethServer package management
Version: 1.3.4
Release: 1%{?dist}
License: GPL
Source: %{name}-%{version}.tar.gz
BuildArch: noarch
URL: %{url_prefix}/%{name} 

Requires: yum
Requires: python, python-simplejson
Requires: nethserver-lib

BuildRequires: nethserver-devtools

%description
Yum plugin for NethServer package management

%prep
%setup

%build
perl createlinks

%install
/bin/rm -rf $RPM_BUILD_ROOT
(cd root   ; /usr/bin/find . -depth -print | /bin/cpio -dump $RPM_BUILD_ROOT)

%{genfilelist} \
    --file '/sbin/e-smith/event_queue' 'attr(0555,root,root)'  \
    $RPM_BUILD_ROOT > %{name}-%{version}-%{release}-filelist
echo "%doc COPYING"          >> %{name}-%{version}-%{release}-filelist


%files -f %{name}-%{version}-%{release}-filelist
%defattr(-,root,root)


%changelog
* Tue Nov 11 2014 Davide Principi <davide.principi@nethesis.it> - 1.3.4-1.ns6
- Wrong signal-event order under certain circumstance  - Enhancement #2904 [NethServer]

* Thu Oct 02 2014 Davide Principi <davide.principi@nethesis.it> - 1.3.3-1.ns6
- Handle nethserver-firewall-base uninstallation - Enhancement #2873 [NethServer]

* Wed Aug 20 2014 Davide Principi <davide.principi@nethesis.it> - 1.3.2-1.ns6
- Embed Nethgui 1.6.0 into httpd-admin RPM - Enhancement #2820 [NethServer]

* Mon Mar 24 2014 Davide Principi <davide.principi@nethesis.it> - 1.3.1-1.ns6
- New pkginfo compsdump command - Feature #2694 [NethServer]

* Wed Feb 05 2014 Davide Principi <davide.principi@nethesis.it> - 1.3.0-1.ns6
- Free package names for nethserver_events yum plugin - Enhancement #2546 [NethServer]

* Wed Dec 18 2013 Davide Principi <davide.principi@nethesis.it> - 1.2.0-1.ns6
- Process tracking and notifications - Feature #2029 [NethServer]

* Thu Jul 25 2013 Davide Principi <davide.principi@nethesis.it> - 1.1.1-1.ns6
- /etc/sudoers template moved to nethserver-base project - Feature #1767 [NethServer]

* Mon Jul 15 2013 Davide Principi <davide.principi@nethesis.it> - 1.1.0-1.ns6
- pkgaction: support multiple install, remove, update commands. - Enhancement #1748 [NethServer]
- Base: new PackageManager UI module - Feature #1767 [NethServer]

* Tue May  7 2013 Davide Principi <davide.principi@nethesis.it> - 1.0.4-1.ns6
- Ensure package list is sorted respecting RPM dependencies #1870

* Thu May 02 2013 Giacomo Sanchietti <giacomo.sanchietti@nethesis.it> - 1.0.3-1.ns6
- Execute firewall-adjust and runlevel-adjust only on a nethserver package installation

* Tue Apr 30 2013 Giacomo Sanchietti <giacomo.sanchietti@nethesis.it> - 1.0.2-1.ns6
- Rebuild for automatic package handling. #1870
- Remove event-queue logic, add new behavior #1871

* Tue Mar 19 2013 Giacomo Sanchietti <giacomo.sanchietti@nethesis.it> - 1.0.1-1
- First stable release

