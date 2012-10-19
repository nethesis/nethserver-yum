from subprocess import call
from yum.plugins import TYPE_CORE

requires_api_version = '2.1'
plugin_type = (TYPE_CORE,)

def pretrans_hook(conduit):
    if conduit.confBool("main", "verbose", default=0): #if verbose
        conduit.info(2, "Enabling NethServer queue")
    call("/sbin/e-smith/event-queue enable", shell=True)

def posttrans_hook(conduit):
    if conduit.confBool("main", "verbose", default=0): #if verbose
        conduit.info(2, "Executing NethServer queue")
    call("/sbin/e-smith/event-queue signal", shell=True)

def close_hook(conduit):
    call("/sbin/e-smith/event-queue enable", shell=True)
    if conduit.confBool("main", "verbose", default=0): #if verbose
        conduit.info(2, "Flushing NethServer queue")
    # clean up queue
    call("/sbin/e-smith/event-queue disable", shell=True)


