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

requires_api_version = '2.1'
plugin_type = (TYPE_CORE,)
EVENT_QUEUE_CMD = '/sbin/e-smith/event-queue'

def pretrans_hook(conduit):
    if conduit.confBool("main", "verbose", default=0): #if verbose
        conduit.info(2, "Enabling NethServer queue")
    if hasEventCommand():
        call(EVENT_QUEUE_CMD + " enable", shell=True)

def posttrans_hook(conduit):
    if conduit.confBool("main", "verbose", default=0): #if verbose
        conduit.info(2, "Executing NethServer queue")
    if hasEventCommand():
        call(EVENT_QUEUE_CMD + " signal", shell=True)

def close_hook(conduit):
    if conduit.confBool("main", "verbose", default=0): #if verbose
        conduit.info(2, "Flushing NethServer queue")
    if hasEventCommand():
        # clean up queue
        call(EVENT_QUEUE_CMD + " disable", shell=True) 

def hasEventCommand():
    return os.path.isfile(EVENT_QUEUE_CMD)
