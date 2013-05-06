#
# Copyright (C) 2012 Nethesis S.r.l.
# http://www.nethesis.it - support@nethesis.it
# 
# This script is part of NethServer.
# 
# NethServer is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License,
# or any later version.
# 
# NethServer is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# 
# You should have received a copy of the GNU General Public License
# along with NethServer.  If not, see <http://www.gnu.org/licenses/>.
#

from subprocess import call
from yum.plugins import TYPE_CORE
import os.path
import os
import rpm

requires_api_version = '2.1'
plugin_type = (TYPE_CORE,)

signal_event = '/sbin/e-smith/signal-event'
events_dir = '/etc/e-smith/events'

def read_package_list():
    """
    Query RPM db for any nethserver* package and return the list of
    package names, preserving the dependency order. RPM 'Require' tags
    are parsed.
    """

    list = []
    ts = rpm.TransactionSet()
    mi = ts.dbMatch()
    for h in mi:
        if not h['name'].startswith('nethserver'): 
            continue

        for dep in h[rpm.RPMTAG_REQUIRENAME]:
            if not dep.startswith('nethserver'): 
                continue

            try:
                # insert deps before the package name
                list.insert(list.index(h['name']), dep)
            except ValueError:
                list.append(dep)

        if not h['name'] in list:
            list.append(h['name'])

    # Reduce the given list preserving the element order
    s = set()
    return filter(lambda x: x not in s and not s.add(x) , list) 


def reconfigure_all_packages():
    """Signal *-update event for any nethserver* package"""

    for p in read_package_list():
        event = "%s-update" % p
        if os.path.isdir('/etc/e-smith/events/%s' % event):
            os.spawnl(os.P_WAIT, signal_event, signal_event, event)


def adjust_all_services():
    """Signal firewall-adjust and runlevel-adjust system events"""

    if os.path.isdir(events_dir + "/firewall-adjust"):
        os.spawnl(os.P_WAIT, signal_event, signal_event, "firewall-adjust")
    if os.path.isdir(events_dir + "/runlevel-adjust"):
        os.spawnl(os.P_WAIT, signal_event, signal_event, "runlevel-adjust")



def posttrans_hook(conduit):
    """ The yum post-RPM-transaction hook """

    installed = []
    erased = []

    if conduit.confBool("main", "verbose", default=0): #if verbose
        conduit.info(2, "Executing NethServer queue")

    ts = conduit.getTsInfo()

    for tsmem in ts.getMembers():
        if tsmem.name.startswith('nethserver'):
            if tsmem.ts_state == 'i' or tsmem.ts_state == 'u':
                installed.append(tsmem.name)
            elif tsmem.ts_state == 'e':
                erased.append(tsmem.name)

    # Get the list of installed/update packages respecting the
    # dependency sorting:
    installed = filter(lambda x: x in installed, read_package_list())
    
    for ipkg in installed:
        event = "%s-update" % ipkg
        if os.path.isdir(events_dir + "/%s" % event):
            if conduit.confBool("main", "verbose", default=0): #if verbose
                conduit.info(2, "Executing signal-event %s" % event)
            os.spawnl(os.P_WAIT, signal_event, signal_event, event)

    if len(erased) > 0:
        reconfigure_all_packages()

    if len(installed) > 0 or len(erased) > 0:
        adjust_all_services()
