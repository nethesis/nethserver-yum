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

from yum.plugins import TYPE_CORE
import os.path
import os
import rpm
import syslog
import nethserver.ptrack

requires_api_version = '2.1'
plugin_type = (TYPE_CORE,)

signal_event = '/sbin/e-smith/signal-event'
events_dir = '/etc/e-smith/events'

def has_update_event(x):
    """
    Determine if the given x has a corresponding e-smith *-update event
    x can be either a package name (string) or an rpm.hdr object
    """
    if(isinstance(x, rpm.hdr)):
        o = x
        n = x['name']
    elif(isinstance(x, str)):
        n = x
        o = None
    else:
        raise Exception("invalid object")

    if n not in has_update_event.cache:
        if o is None:
            try:
                o = rpm.TransactionSet().dbMatch('name', n).next()
            except StopIteration:
                o = {'FILENAMES': []}
        has_update_event.cache[n] = '%s/%s%s' % (events_dir, n, '-update') in o['FILENAMES']

    return has_update_event.cache[n]


has_update_event.cache = {}

def read_package_list():
    """
    Query RPM db for any nethserver* package and return the list of
    package names, preserving the dependency order. RPM 'Require' tags
    are parsed.
    """

    packages = []
    ts = rpm.TransactionSet()
    mi = ts.dbMatch()
    for h in mi:
        if not has_update_event(h['name']):
            continue

        for dep in h[rpm.RPMTAG_REQUIRENAME]:
            if not has_update_event(dep):
                continue

            try:
                # insert deps before the package name
                packages.insert(packages.index(h['name']), dep)
            except ValueError:
                packages.append(dep)

        if not h['name'] in packages:
            packages.append(h['name'])

    # Reduce the package list preserving the element order:
    return list_unique(packages)

def list_unique(l):
    s = set()
    return filter(lambda x: x not in s and not s.add(x) , l) 

def filter_update_events(packages):
    return filter(lambda e: os.path.isdir(events_dir + "/" + e), map(lambda p: "%s-update" % p, packages))

def posttrans_hook(conduit):
    """ The yum post-RPM-transaction hook """

    installed = []
    erased = []
    events = []
    nethserver_packages = read_package_list()

    if conduit.confBool("main", "verbose", default=0): #if verbose
        conduit.info(2, "Signaling NethServer update events")

    ts = conduit.getTsInfo()

    for tsmem in ts.getMembers():
        if tsmem.name.startswith('nethserver'):
            if tsmem.ts_state == 'i' or tsmem.ts_state == 'u':
                installed.append(tsmem.name)
            elif tsmem.ts_state == 'e':
                erased.append(tsmem.name)

    if len(installed) > 0:
        # Get the list of installed/update packages respecting the
        # dependency sorting:
        installed = filter(lambda x: x in installed, nethserver_packages)
        events.extend(filter_update_events(installed))

    if len(erased) > 0:
        # If a nethserver package was removed add ALL remaining
        # nethserver packages to the event list:
        events.extend(filter_update_events(nethserver_packages))

    if len(events) > 0:
        # Adjust firewall and services if something was updated:
        if os.path.isdir(events_dir + "/firewall-adjust"):
            events.append('firewall-adjust')
        if os.path.isdir(events_dir + "/runlevel-adjust"):
            events.append('runlevel-adjust')

    run_events(events)


def run_events(events):
    # Remove duplicate events, preserving order:
    events = list_unique(events)

    tasks = {}
    penv = os.environ.copy()
    tracker = nethserver.ptrack.TrackerClient()
    success = True

    for event in events:
        tasks[event] = tracker.declare_task("Event %s" % event)

    # Execute the event list:
    for event in events:
        if(event in tasks):
            penv['PTRACK_TASKID'] = str(tasks[event])
        elif('PTRACK_TASKID' in penv):
            del penv['PTRACK_TASKID']
        event_exit_code = os.spawnle(os.P_WAIT, signal_event, signal_event, event, penv)
        tracker.set_task_done(tasks[event], "", event_exit_code)
        if(event_exit_code != 0):
            success = False

    return success
