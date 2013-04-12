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

def insertBefore(list, el, search):
    i = 0
    k = -1
    for l in list:
        if l == search:
            k = i
        i=i+1
    if k < 0:
        list.append(el)
    else:
        list.insert(k,el)
    return list

def uniq(list):
    seen = set()
    seen_add = seen.add
    return [ x for x in list if x not in seen and not seen_add(x)]

def postUpgrade():
    list = ['nethserver-lib']
    ts = rpm.TransactionSet()
    mi = ts.dbMatch()
    for h in mi:
        if not h['name'].startswith('nethserver'): continue
        for dep in h[rpm.RPMTAG_REQUIRENAME]:
            if not dep.startswith('nethserver'): continue
            insertBefore(list,dep,h['name']);
        list.append(h['name'])

    for p in uniq(list):
        event = "%s-update" % p
        if os.path.isdir('/etc/e-smith/events/%s' % event):
            os.spawnl(os.P_WAIT, signal_event, signal_event, event)


requires_api_version = '2.1'
plugin_type = (TYPE_CORE,)
signal_event = '/sbin/e-smith/signal-event'


def posttrans_hook(conduit):
    installed = []
    erased = []
    isUninstall = False
    if conduit.confBool("main", "verbose", default=0): #if verbose
        conduit.info(2, "Executing NethServer queue")
    ts = conduit.getTsInfo()

    for tsmem in ts.getMembers():
        if tsmem.name.startswith('nethserver'):
            if tsmem.ts_state == 'i' or tsmem.ts_state == 'u':
                installed.append(tsmem.name)
            elif tsmem.ts_state == 'e':
                isUninstall = True
                erased.append(tsmem.name)

    installed.reverse()
    for ipkg in installed:
        event = "%s-update" % ipkg
        if os.path.isdir("/etc/e-smith/events/%s" % event):
            if conduit.confBool("main", "verbose", default=0): #if verbose
                conduit.info(2, "Executing signal-event %s" % event)
            os.spawnl(os.P_WAIT, signal_event, signal_event, event)

    if isUninstall:
        postUpgrade()

    if os.path.isdir("/etc/e-smith/events/firewall-adjust"):
        os.spawnl(os.P_WAIT, signal_event, signal_event, "firewall-adjust")
    if os.path.isdir("/etc/e-smith/events/runlevel-adjust"):
        os.spawnl(os.P_WAIT, signal_event, signal_event, "runlevel-adjust")

