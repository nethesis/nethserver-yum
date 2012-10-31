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
