# vim: noexpandtab tabstop=4

import os
from yum.constants import *
from yum.plugins import PluginYumExit
from yum.plugins import TYPE_CORE

requires_api_version = '2.1'
plugin_type = (TYPE_CORE,)

events_path  = '/etc/e-smith/events'
initialize_database = events_path + '/actions/initialize-default-databases'
navigation_conf = events_path + '/actions/navigation-conf'
signal_event = '/sbin/e-smith/signal-event'
config_set = '/sbin/e-smith/config'
status_file  = '/var/run/yum.status'

smechange = False
nethchange = False
ourfile = False

def report_yum_status(status):
	fileHandle = open(status_file, 'w')
	fileHandle.write(status)
	fileHandle.close()

def predownload_hook(conduit):
	report_yum_status('predownload')

def postdownload_hook(conduit):
	report_yum_status('postdownload')

def prereposetup_hook(conduit):
	global ourfile
	ourfile = True
	report_yum_status('prereposetup')

def postreposetup_hook(conduit):
	report_yum_status('postreposetup')

def exclude_hook(conduit):
	report_yum_status('exclude')

def preresolve_hook(conduit):
	report_yum_status('preresolve')

def postresolve_hook(conduit):
	report_yum_status('postresolve')

def pretrans_hook(conduit):
	report_yum_status('pretrans')
	ts = conduit.getTsInfo()

def posttrans_hook(conduit):
	global smechange
	global nethchange
	report_yum_status('posttrans')
	ts = conduit.getTsInfo()
	for tsmem in ts.getMembers():
		smeevent = 'yum-reconfigure-' + tsmem.name
		if os.path.isdir(events_path + '/' + smeevent):
			print "smeservers signal-event: " + smeevent
			os.spawnl(os.P_WAIT,
				signal_event, signal_event, smeevent)

		(n, a, e, v, r) = tsmem.po.pkgtup
		if n.startswith('sme') or n.startswith('e-smith') or r.endswith('sme'):
			if r.endswith('nh'):
				nethchange = True
			elif tsmem.output_state != TS_UPDATED:
				smechange = True

#	if smechange:
#		os.spawnl(os.P_WAIT, config_set, config_set, 'set', 'UnsavedChanges', 'yes')
	if smechange or nethchange:
		os.spawnl(os.P_WAIT, initialize_database, initialize_database)
		os.spawnl(os.P_WAIT, navigation_conf, navigation_conf)

def close_hook(conduit):
	if ourfile and os.path.isfile('/var/run/yum.status'):
		os.unlink('/var/run/yum.status')

#	if smechange:
#		print "\n=============================================================="
#		print "WARNING: You now need to run BOTH of the following commands"
#		print "to ensure consistent system state:\n"
#		print "signal-event post-upgrade; signal-event reboot\n"
#		print "You should run these commands unless you are certain that"
#		print "yum made no changes to your system."
#		print "=============================================================="

