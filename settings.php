<?php /*
*    Pi-hole: A black hole for Internet advertisements
*    (c) 2017 Pi-hole, LLC (https://pi-hole.net)
*    Network-wide ad blocking via your own hardware.
*
*    This file is copyright under the latest version of the EUPL.
*    Please see LICENSE file for your rights under this license. */
require "scripts/pi-hole/php/header.php";
require "scripts/pi-hole/php/savesettings.php";
// Reread ini file as things might have been changed
$setupVars = parse_ini_file("/etc/pihole/setupVars.conf");
if(is_readable($piholeFTLConfFile))
{
	$piholeFTLConf = parse_ini_file($piholeFTLConfFile);
}
else
{
	$piholeFTLConf = array();
}

// Handling of PHP internal errors
$last_error = error_get_last();
if($last_error["type"] === E_WARNING || $last_error["type"] === E_ERROR)
{
	$error .= "There was a problem applying your settings.<br>Debugging information:<br>PHP error (".htmlspecialchars($last_error["type"])."): ".htmlspecialchars($last_error["message"])." in ".htmlspecialchars($last_error["file"]).":".htmlspecialchars($last_error["line"]);
}

?>
<style type="text/css">
	.tooltip-inner {
		max-width: none;
		white-space: nowrap;
	}
</style>

<?php // Check if ad lists should be updated after saving ...
if (isset($_POST["submit"])) {
    if ($_POST["submit"] == "saveupdate") {
        // If that is the case -> refresh to the gravity page and start updating immediately
        ?>
        <meta http-equiv="refresh" content="1;url=gravity.php?go">
    <?php }
} ?>

<?php if (isset($debug)) { ?>
    <div id="alDebug" class="alert alert-warning alert-dismissible fade in" role="alert">
        <button type="button" class="close" data-hide="alert" aria-label="Close"><span aria-hidden="true">&times;</span>
        </button>
        <h4><i class="icon fa fa-exclamation-triangle"></i> Debug</h4>
        <pre><?php print_r($_POST); ?></pre>
    </div>
<?php } ?>

<?php if (strlen($success) > 0) { ?>
    <div id="alInfo" class="alert alert-info alert-dismissible fade in" role="alert">
        <button type="button" class="close" data-hide="alert" aria-label="Close"><span aria-hidden="true">&times;</span>
        </button>
        <h4><i class="icon fa fa-info"></i> Info</h4>
        <?php echo $success; ?>
    </div>
<?php } ?>

<?php if (strlen($error) > 0) { ?>
    <div id="alError" class="alert alert-danger alert-dismissible fade in" role="alert">
        <button type="button" class="close" data-hide="alert" aria-label="Close"><span aria-hidden="true">&times;</span>
        </button>
        <h4><i class="icon fa fa-ban"></i> Error</h4>
        <?php echo $error; ?>
    </div>
<?php } ?>


<?php
// Networking
if (isset($setupVars["PIHOLE_INTERFACE"])) {
    $piHoleInterface = $setupVars["PIHOLE_INTERFACE"];
} else {
    $piHoleInterface = "unknown";
}
if (isset($setupVars["IPV4_ADDRESS"])) {
    $piHoleIPv4 = $setupVars["IPV4_ADDRESS"];
} else {
    $piHoleIPv4 = "unknown";
}
$IPv6connectivity = false;
if (isset($setupVars["IPV6_ADDRESS"])) {
    $piHoleIPv6 = $setupVars["IPV6_ADDRESS"];
    sscanf($piHoleIPv6, "%2[0-9a-f]", $hexstr);
    if (strlen($hexstr) == 2) {
        // Convert HEX string to number
        $hex = hexdec($hexstr);
        // Global Unicast Address (2000::/3, RFC 4291)
        $GUA = (($hex & 0x70) === 0x20);
        // Unique Local Address   (fc00::/7, RFC 4193)
        $ULA = (($hex & 0xfe) === 0xfc);
        if ($GUA || $ULA) {
            // Scope global address detected
            $IPv6connectivity = true;
        }
    }
} else {
    $piHoleIPv6 = "unknown";
}
$hostname = trim(file_get_contents("/etc/hostname"), "\x00..\x1F");
?>

<?php
// DNS settings
$DNSservers = [];
$DNSactive = [];

$i = 1;
while (isset($setupVars["PIHOLE_DNS_" . $i])) {
    if (isinserverlist($setupVars["PIHOLE_DNS_" . $i])) {
        array_push($DNSactive, $setupVars["PIHOLE_DNS_" . $i]);
    } elseif (strpos($setupVars["PIHOLE_DNS_" . $i], ".") !== false) {
        if (!isset($custom1)) {
            $custom1 = $setupVars["PIHOLE_DNS_" . $i];
        } else {
            $custom2 = $setupVars["PIHOLE_DNS_" . $i];
        }
    } elseif (strpos($setupVars["PIHOLE_DNS_" . $i], ":") !== false) {
        if (!isset($custom3)) {
            $custom3 = $setupVars["PIHOLE_DNS_" . $i];
        } else {
            $custom4 = $setupVars["PIHOLE_DNS_" . $i];
        }
    }
    $i++;
}

if (isset($setupVars["DNS_FQDN_REQUIRED"])) {
    if ($setupVars["DNS_FQDN_REQUIRED"]) {
        $DNSrequiresFQDN = true;
    } else {
        $DNSrequiresFQDN = false;
    }
} else {
    $DNSrequiresFQDN = true;
}

if (isset($setupVars["DNS_BOGUS_PRIV"])) {
    if ($setupVars["DNS_BOGUS_PRIV"]) {
        $DNSbogusPriv = true;
    } else {
        $DNSbogusPriv = false;
    }
} else {
    $DNSbogusPriv = true;
}

if (isset($setupVars["DNSSEC"])) {
    if ($setupVars["DNSSEC"]) {
        $DNSSEC = true;
    } else {
        $DNSSEC = false;
    }
} else {
    $DNSSEC = false;
}

if (isset($setupVars["DNSMASQ_LISTENING"])) {
    if ($setupVars["DNSMASQ_LISTENING"] === "single") {
        $DNSinterface = "single";
    } elseif ($setupVars["DNSMASQ_LISTENING"] === "all") {
        $DNSinterface = "all";
    } else {
        $DNSinterface = "local";
    }
} else {
    $DNSinterface = "single";
}
if (isset($setupVars["CONDITIONAL_FORWARDING"]) && ($setupVars["CONDITIONAL_FORWARDING"] == 1)) {
    $conditionalForwarding = true;
    $conditionalForwardingDomain = $setupVars["CONDITIONAL_FORWARDING_DOMAIN"];
    $conditionalForwardingIP = $setupVars["CONDITIONAL_FORWARDING_IP"];
} else {
    $conditionalForwarding = false;
}
?>

<?php
// Query logging
if (isset($setupVars["QUERY_LOGGING"])) {
    if ($setupVars["QUERY_LOGGING"] == 1) {
        $piHoleLogging = true;
    } else {
        $piHoleLogging = false;
    }
} else {
    $piHoleLogging = true;
}
?>

<?php
// Excluded domains in API Query Log call
if (isset($setupVars["API_EXCLUDE_DOMAINS"])) {
    $excludedDomains = explode(",", $setupVars["API_EXCLUDE_DOMAINS"]);
} else {
    $excludedDomains = [];
}

// Exluded clients in API Query Log call
if (isset($setupVars["API_EXCLUDE_CLIENTS"])) {
    $excludedClients = explode(",", $setupVars["API_EXCLUDE_CLIENTS"]);
} else {
    $excludedClients = [];
}

// Exluded clients
if (isset($setupVars["API_QUERY_LOG_SHOW"])) {
    $queryLog = $setupVars["API_QUERY_LOG_SHOW"];
} else {
    $queryLog = "all";
}

// Privacy Mode
if (isset($setupVars["API_PRIVACY_MODE"])) {
    $privacyMode = $setupVars["API_PRIVACY_MODE"];
} else {
    $privacyMode = false;
}

?>

<?php
if (isset($_GET['tab']) && in_array($_GET['tab'], array("sysadmin", "blocklists", "dns", "api", "privacy", "teleporter"))) {
    $tab = $_GET['tab'];
} else {
    $tab = "sysadmin";
}
?>
<div class="row justify-content-md-center">
    <div class="col-md-12">
        <div class="nav-tabs-custom">
            <ul class="nav nav-tabs">
                <li<?php if($tab === "sysadmin"){ ?> class="active"<?php } ?>><a data-toggle="tab" href="#sysadmin">System</a></li>
                <li<?php if($tab === "blocklists"){ ?> class="active"<?php } ?>><a data-toggle="tab" href="#blocklists">Blocklists</a></li>
                <li<?php if($tab === "dns"){ ?> class="active"<?php } ?>><a data-toggle="tab" href="#dns">DNS</a></li>
                <li<?php if($tab === "api"){ ?> class="active"<?php } ?>><a data-toggle="tab" href="#api">API / Web interface</a></li>
                <li<?php if($tab === "privacy"){ ?> class="active"<?php } ?>><a data-toggle="tab" href="#privacy">Privacy</a></li>
                <li<?php if($tab === "teleporter"){ ?> class="active"<?php } ?>><a data-toggle="tab" href="#teleporter">Teleporter</a></li>
            </ul>
            <div class="tab-content">
                <!-- ######################################################### Blocklists ######################################################### -->
                <div id="blocklists" class="tab-pane fade<?php if($tab === "blocklists"){ ?> in active<?php } ?>">
                    <form role="form" method="post">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="box">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">Blocklists used to generate Pi-hole's Gravity: <?php echo count($adlist); ?></h3>
                                    </div>
                                    <div class="box-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped table-bordered dt-responsive nowrap">
                                                <thead>
                                                <tr>
                                                    <th>Enabled</th>
                                                    <th>List</th>
                                                    <th style="width:1%">Delete</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($adlist as $key => $value) { ?>
                                                        <tr>
                                                            <td>
                                                                <input type="checkbox" name="adlist-enable-<?php echo $key; ?>" <?php if ($value[0]){ ?>checked<?php } ?>>
                                                            </td>
                                                            <td>
                                                                <a href="<?php echo htmlentities($value[1]); ?>" target="_new" id="adlist-text-<?php echo $key; ?>"><?php echo htmlentities($value[1]); ?></a>
                                                            </td>
                                                            <td class="text-center">
                                                                <button class="btn btn-danger btn-xs" id="adlist-btn-<?php echo $key; ?>">
                                                                    <span class="glyphicon glyphicon-trash"></span>
                                                                </button>
                                                                <input type="checkbox" name="adlist-del-<?php echo $key; ?>" hidden>
                                                            </td>
                                                        </tr>
                                                    <?php } ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="form-group">
                                            <textarea name="newuserlists" class="form-control" rows="1" placeholder="Enter one URL per line to add new blocklists"></textarea>
                                        </div>
                                        <input type="hidden" name="field" value="adlists">
                                        <input type="hidden" name="token" value="<?php echo $token ?>">
                                    </div>
                                    <div class="box-footer clearfix">
                                        <button type="submit" class="btn btn-primary" name="submit" value="save" id="blockinglistsave">Save</button>
                                        <span><strong>Important: </strong>Save and Update when you're done!</span>
                                        <button type="submit" class="btn btn-primary pull-right" name="submit" id="blockinglistsaveupdate" value="saveupdate">Save and Update</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <!-- ######################################################### DHCP ######################################################### -->
                <div id="dns" class="tab-pane fade<?php if($tab === "dns"){ ?> in active<?php } ?>">
                    <form role="form" method="post">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="box box-warning">
                                    <div class="box-header with-border">
                                        <h1 class="box-title">Upstream DNS Servers</h1>
                                    </div>
                                    <div class="box-body">
                                        <div class="row">
                                            <div class="col-sm-12">
                                                <table class="table table-bordered">
                                                    <tr>
                                                        <th colspan="2">IPv4</th>
                                                        <th colspan="2">IPv6</th>
                                                        <th>Name</th>
                                                    </tr>
                                                    <?php foreach ($DNSserverslist as $key => $value) { ?>
                                                    <tr>
                                                    <?php if (isset($value["v4_1"])) { ?>
                                                        <td title="<?php echo $value["v4_1"]; ?>">
                                                            <input type="checkbox" name="DNSserver<?php echo $value["v4_1"]; ?>" value="true"
                                                                   <?php if (in_array($value["v4_1"], $DNSactive)){ ?>checked<?php } ?>>
                                                        </td>
                                                    <?php } else { ?>
                                                        <td></td>
                                                    <?php } ?>
                                                    <?php if (isset($value["v4_2"])) { ?>
                                                        <td title="<?php echo $value["v4_2"]; ?>">
                                                            <input type="checkbox" name="DNSserver<?php echo $value["v4_2"]; ?>" value="true"
                                                                   <?php if (in_array($value["v4_2"], $DNSactive)){ ?>checked<?php } ?>>
                                                        </td>
                                                    <?php } else { ?>
                                                        <td></td>
                                                    <?php } ?>
                                                    <?php if (isset($value["v6_1"])) { ?>
                                                        <td title="<?php echo $value["v6_1"]; ?>">
                                                            <input type="checkbox" name="DNSserver<?php echo $value["v6_1"]; ?>" value="true"
                                                                   <?php if (in_array($value["v6_1"], $DNSactive) && $IPv6connectivity){ ?>checked<?php }
                                                                         if (!$IPv6connectivity) { ?> disabled <?php } ?>>
                                                        </td>
                                                    <?php } else { ?>
                                                        <td></td>
                                                    <?php } ?>
                                                    <?php if (isset($value["v6_2"])) { ?>
                                                        <td title="<?php echo $value["v6_2"]; ?>">
                                                            <input type="checkbox" name="DNSserver<?php echo $value["v6_2"]; ?>" value="true"
                                                                   <?php if (in_array($value["v6_2"], $DNSactive) && $IPv6connectivity){ ?>checked<?php }
                                                                if (!$IPv6connectivity) { ?> disabled <?php } ?>>
                                                        </td>
                                                    <?php } else { ?>
                                                        <td></td>
                                                    <?php } ?>
                                                        <td><?php echo $key; ?></td>
                                                    </tr>
                                                    <?php } ?>
                                                </table>
                                                <p>ECS (Extended Client Subnet) defines a mechanism for recursive resolvers to send partial client IP address information to authoritative DNS name servers. Content Delivery Networks (CDNs) and latency-sensitive services use this to give geo-located responses when responding to name lookups coming through public DNS resolvers. <em>Note that ECS may result in reduced privacy.</em></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="box box-warning">
                                    <div class="box-header with-border">
                                        <h1 class="box-title">Upstream DNS Servers</h1>
                                    </div>
                                    <div class="box-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Custom 1 (IPv4)</label>
                                                    <div class="input-group">
                                                        <div class="input-group-addon">
                                                            <input type="checkbox" name="custom1" value="Customv4"
                                                                   <?php if (isset($custom1)){ ?>checked<?php } ?>>
                                                        </div>
                                                        <input type="text" name="custom1val" class="form-control"
                                                               <?php if (isset($custom1)){ ?>value="<?php echo $custom1; ?>"<?php } ?>>
                                                    </div>
                                                    <label>Custom 2 (IPv4)</label>
                                                    <div class="input-group">
                                                        <div class="input-group-addon">
                                                            <input type="checkbox" name="custom2" value="Customv4"
                                                                   <?php if (isset($custom2)){ ?>checked<?php } ?>>
                                                        </div>
                                                        <input type="text" name="custom2val" class="form-control"
                                                               <?php if (isset($custom2)){ ?>value="<?php echo $custom2; ?>"<?php } ?>>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Custom 3 (IPv6)</label>
                                                    <div class="input-group">
                                                        <div class="input-group-addon">
                                                            <input type="checkbox" name="custom3" value="Customv6"
                                                                   <?php if (isset($custom3)){ ?>checked<?php } ?>>
                                                        </div>
                                                        <input type="text" name="custom3val" class="form-control"
                                                               <?php if (isset($custom3)){ ?>value="<?php echo $custom3; ?>"<?php } ?>>
                                                    </div>
                                                    <label>Custom 4 (IPv6)</label>
                                                    <div class="input-group">
                                                        <div class="input-group-addon">
                                                            <input type="checkbox" name="custom4" value="Customv6"
                                                                   <?php if (isset($custom4)){ ?>checked<?php } ?>>
                                                        </div>
                                                        <input type="text" name="custom4val" class="form-control"
                                                               <?php if (isset($custom4)){ ?>value="<?php echo $custom4; ?>"<?php } ?>>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="box box-warning">
                                    <div class="box-header with-border">
                                        <h1 class="box-title">Interface listening behavior</h1>
                                    </div>
                                    <div class="box-body">
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <div class="form-group">
                                                    <div class="radio">
                                                        <label><input type="radio" name="DNSinterface" value="local"
                                                                      <?php if ($DNSinterface == "local"){ ?>checked<?php } ?>>
                                                               <strong>Listen on all interfaces</strong>
                                                               <br>Allows only queries from devices that are at most one hop away (local devices)</label>
                                                    </div>
                                                    <div class="radio">
                                                        <label><input type="radio" name="DNSinterface" value="single"
                                                                      <?php if ($DNSinterface == "single"){ ?>checked<?php } ?>>
                                                               <strong>Listen only on interface <?php echo htmlentities($piHoleInterface); ?></strong>
                                                        </label>
                                                    </div>
                                                    <div class="radio">
                                                        <label><input type="radio" name="DNSinterface" value="all"
                                                                      <?php if ($DNSinterface == "all"){ ?>checked<?php } ?>>
                                                               <strong>Listen on all interfaces, permit all origins</strong>
                                                        </label>
                                                    </div>
                                                </div>
                                                <p>Note that the last option should not be used on devices which are
                                                   directly connected to the Internet. This option is safe if your
                                                   Pi-hole is located within your local network, i.e. protected behind
                                                   your router, and you have not forwarded port 53 to this device. In
                                                   virtually all other cases you have to make sure that your Pi-hole is
                                                   properly firewalled.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="box box-warning">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">Advanced DNS settings</h3>
                                    </div>
                                    <div class="box-body">
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <div class="form-group">
                                                    <div class="checkbox">
                                                        <label><input type="checkbox" name="DNSrequiresFQDN" title="domain-needed"
                                                                      <?php if ($DNSrequiresFQDN){ ?>checked<?php }
                                                                      ?>>Never forward non-FQDNs</label>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <div class="checkbox">
                                                        <label><input type="checkbox" name="DNSbogusPriv" title="bogus-priv"
                                                                      <?php if ($DNSbogusPriv){ ?>checked<?php }
                                                                      ?>>Never forward reverse lookups for private IP ranges</label>
                                                    </div>
                                                </div>
                                                <p>Note that enabling these two options may increase your privacy
                                                   slightly, but may also prevent you from being able to access
                                                   local hostnames if the Pi-hole is not used as DHCP server</p>
                                                <div class="form-group">
                                                    <div class="checkbox">
                                                        <label><input type="checkbox" name="DNSSEC"
                                                                      <?php if ($DNSSEC){ ?>checked<?php }
                                                                      ?>>Use DNSSEC</label>
                                                    </div>
                                                </div>
                                                <p>Validate DNS replies and cache DNSSEC data. When forwarding DNS
                                                   queries, Pi-hole requests the DNSSEC records needed to validate
                                                   the replies. If a domain fails validation or the upstream does not
                                                   support DNSSEC, this setting can cause issues resolving domains.
                                                   Use Google, Cloudflare, DNS.WATCH, Quad9, or another DNS
                                                   server which supports DNSSEC when activating DNSSEC. Note that
                                                   the size of your log might increase significantly
                                                   when enabling DNSSEC. A DNSSEC resolver test can be found
                                                   <a href="http://dnssec.vs.uni-due.de/" target="_blank">here</a>.</p>
                                                <label>Conditional Forwarding</label>
                                                <p>If not configured as your DHCP server, Pi-hole won't be able to
                                                   determine the names of devices on your local network.  As a
                                                   result, tables such as Top Clients will only show IP addresses.</p>
                                                <p>One solution for this is to configure Pi-hole to forward these
	                                                 requests to your DHCP server (most likely your router), but only for devices on your
	                                                 home network.  To configure this we will need to know the IP
	                                                 address of your DHCP server and the name of your local network.</p>
                                                <p>Note: The local domain name must match the domain name specified
                                                        in your DHCP server, likely found within the DHCP settings.</p>
                                                <div class="form-group">
                                                    <div class="checkbox">
                                                        <label><input type="checkbox" name="conditionalForwarding" value="conditionalForwarding"
                                                        <?php if(isset($conditionalForwarding) && ($conditionalForwarding == true)){ ?>checked<?php }
                                                        ?>>Use Conditional Forwarding</label>
                                                    </div>
                                                    <div class="input-group">
                                                      <table class="table table-bordered">
                                                        <tr>
                                                          <th>IP of your router</th>
                                                          <th>Local domain name</th>
                                                        </tr>
                                                        <tr>
                                                          <div class="input-group">
                                                            <td>
                                                              <input type="text" name="conditionalForwardingIP" class="form-control"
                                                              <?php if(isset($conditionalForwardingIP)){ ?>value="<?php echo $conditionalForwardingIP; ?>"<?php } ?>>
                                                            </td>
                                                            <td><input type="text" name="conditionalForwardingDomain" class="form-control" data-mask
                                                              <?php if(isset($conditionalForwardingDomain)){ ?>value="<?php echo $conditionalForwardingDomain; ?>"<?php } ?>>
                                                            </td>
                                                          </div>
                                                        </tr>
                                                      </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="field" value="DNS">
                                <input type="hidden" name="token" value="<?php echo $token ?>">
                                <button type="submit" class="btn btn-primary pull-right">Save</button>
                            </div>
                        </div>
                    </form>
                </div>
                <!-- ######################################################### API and Web ######################################################### -->
                <?php
                // CPU temperature unit
                if (isset($setupVars["TEMPERATUREUNIT"])) {
                    $temperatureunit = $setupVars["TEMPERATUREUNIT"];
                } else {
                    $temperatureunit = "C";
                }

                // Administrator email address
                if (isset($setupVars["ADMIN_EMAIL"])) {
                    $adminemail = $setupVars["ADMIN_EMAIL"];
                } else {
                    $adminemail = "";
                }
                ?>
                <div id="api" class="tab-pane fade<?php if($tab === "api"){ ?> in active<?php } ?>">
                    <div class="row">
                        <div class="col-md-6">
                            <form role="form" method="post">
                                <div class="box box-warning">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">API settings</h3>
                                    </div>
                                    <div class="box-body">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <h4>Top Lists</h4>
                                                <p>Exclude the following domains from being shown in</p>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-xs-12 col-sm-6 col-md-12 col-lg-6">
                                                <div class="form-group">
                                                    <label>Top Domains / Top Advertisers</label>
                                                    <textarea name="domains" class="form-control" placeholder="Enter one domain per line"
                                                              rows="4"><?php foreach ($excludedDomains as $domain) {
                                                                             echo $domain . "\n"; }
                                                                       ?></textarea>
                                                </div>
                                            </div>
                                            <div class="col-xs-12 col-sm-6 col-md-12 col-lg-6">
                                                <div class="form-group">
                                                    <label>Top Clients</label>
                                                    <textarea name="clients" class="form-control" placeholder="Enter one IP address or host name per line"
                                                              rows="4"><?php foreach ($excludedClients as $client) {
                                                                             echo $client . "\n"; }
                                                                       ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-12">
                                            <h4>Query Log</h4>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <div class="checkbox"><label><input type="checkbox" name="querylog-permitted" <?php if($queryLog === "permittedonly" || $queryLog === "all"){ ?>checked<?php } ?>> Show permitted domain entries</label></div>
                                                </div>
                                            </div>
                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <div class="checkbox"><label><input type="checkbox" name="querylog-blocked" <?php if($queryLog === "blockedonly" || $queryLog === "all"){ ?>checked<?php } ?>> Show blocked domain entries</label></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="box-footer clearfix">
                                        <input type="hidden" name="field" value="API">
                                        <input type="hidden" name="token" value="<?php echo $token ?>">
                                        <button type="button" class="btn btn-primary api-token">Show API token</button>
                                        <button type="submit" class="btn btn-primary pull-right">Save</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <form role="form" method="post">
                                <div class="box box-warning">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">Web interface settings</h3>
                                    </div>
                                    <div class="box-body">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <h4>Interface appearance</h4>
                                                <div class="form-group">
                                                    <div class="checkbox">
                                                        <label><input type="checkbox" name="boxedlayout" value="yes"
                                                                      <?php if ($boxedlayout){ ?>checked<?php }
                                                                      ?>>Use boxed layout (helpful when working on large screens)</label>
                                                    </div>
                                                </div>
                                                <h4>CPU Temperature Unit</h4>
                                                <div class="form-group">
                                                    <div class="radio">
                                                        <label><input type="radio" name="tempunit" value="C"
                                                                      <?php if ($temperatureunit === "C"){ ?>checked<?php }
                                                                      ?>>Celsius</label>
                                                    </div>
                                                    <div class="radio">
                                                        <label><input type="radio" name="tempunit" value="K"
                                                                      <?php if ($temperatureunit === "K"){ ?>checked<?php }
                                                                      ?>>Kelvin</label>
                                                    </div>
                                                    <div class="radio">
                                                        <label><input type="radio" name="tempunit" value="F"
                                                                      <?php if ($temperatureunit === "F"){ ?>checked<?php }
                                                                      ?>>Fahrenheit</label>
                                                    </div>
                                                </div>
                                                <h4>Administrator Email Address</h4>
                                                <div class="form-group">
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" name="adminemail"
                                                               value="<?php echo htmlspecialchars($adminemail); ?>">
                                                    </div>
                                                </div>
                                                <input type="hidden" name="field" value="webUI">
                                                <input type="hidden" name="token" value="<?php echo $token ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="box-footer clearfix">
                                        <button type="submit" class="btn btn-primary pull-right">Save</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- ######################################################### Privacy (may be expanded further later on) ######################################################### -->
                <?php
                // Get privacy level from piholeFTL config array
                if (isset($piholeFTLConf["PRIVACYLEVEL"])) {
                    $privacylevel = intval($piholeFTLConf["PRIVACYLEVEL"]);
                } else {
                    $privacylevel = 0;
                }
                ?>
                <div id="privacy" class="tab-pane fade<?php if($tab === "privacy"){ ?> in active<?php } ?>">
                    <div class="row">
                        <div class="col-md-12">
                            <form role="form" method="post">
                                <div class="box box-warning">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">Privacy settings</h3>
                                    </div>
                                    <div class="box-body">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <h4>DNS resolver privacy level</h4>
                                                <p>Specify if DNS queries should be anonymized, available options are:
                                                <div class="form-group">
                                                    <div class="radio">
                                                        <label><input type="radio" name="privacylevel" value="0"
                                                                      <?php if ($privacylevel === 0){ ?>checked<?php }
                                                                      ?>>Show everything and record everything<br>Gives maximum amount of statistics</label>
                                                    </div>
                                                    <div class="radio">
                                                        <label><input type="radio" name="privacylevel" value="1"
                                                                      <?php if ($privacylevel === 1){ ?>checked<?php }
                                                                      ?>>Hide domains: Display and store all domains as "hidden"<br>This disables the Top Domains and Top Ads tables on the dashboard</label>
                                                    </div>
                                                    <div class="radio">
                                                        <label><input type="radio" name="privacylevel" value="2"
                                                                      <?php if ($privacylevel === 2){ ?>checked<?php }
                                                                      ?>>Hide domains and clients: Display and store all domains as "hidden" and all clients as "0.0.0.0"<br>This disables all tables on the dashboard</label>
                                                    </div>
                                                    <div class="radio">
                                                        <label><input type="radio" name="privacylevel" value="3"
                                                                      <?php if ($privacylevel === 3){ ?>checked<?php }
                                                                      ?>>Anonymous mode: This disables basically everything except the live anonymous statistics<br>No history is saved at all to the database, and nothing is shown in the query log. Also, there are no top item lists.</label>
                                                    </div>
                                                    <div class="radio">
                                                        <label><input type="radio" name="privacylevel" value="4"
                                                                      <?php if ($privacylevel === 4){ ?>checked<?php }
                                                            ?>>No Statistics mode: This disables all statistics processing. Even the query counters will not be available.<br><strong>Note that regex blocking is not available when query analyzing is disabled.</strong><br>Additionally, you can disable logging to the file <code>/var/log/pihole.log</code> using <code>sudo pihole logging off</code>.</label>
                                                    </div>
                                                </div>
                                                <p>The privacy level may be increased at any time without having to restart the DNS resolver. However, note that the DNS resolver needs to be restarted when lowering the privacy level. This restarting is automatically done when saving.</p>
                                                <?php if($privacylevel > 0 && $piHoleLogging){ ?>
                                                <p class="lookatme">Warning: Pi-hole's query logging is activated. Although the dashboard will hide the requested details, all queries are still fully logged to the pihole.log file.</p>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="box-footer clearfix">
                                        <input type="hidden" name="field" value="privacyLevel">
                                        <input type="hidden" name="token" value="<?php echo $token ?>">
                                        <button type="submit" class="btn btn-primary pull-right">Apply</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- ######################################################### Teleporter ######################################################### -->
                <div id="teleporter" class="tab-pane fade<?php if($tab === "teleporter"){ ?> in active<?php } ?>">
                    <div class="row">
                        <?php if (extension_loaded('Phar')) { ?>
                        <form role="form" method="post" id="takeoutform"
                              action="scripts/pi-hole/php/teleporter.php"
                              target="_blank" enctype="multipart/form-data">
                            <input type="hidden" name="token" value="<?php echo $token ?>">
                            <div class="col-lg-6 col-md-12">
                                <div class="box box-warning">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">Teleporter Export</h3>
                                    </div>
                                    <div class="box-body">
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <p>Export your Pi-hole lists as downloadable archive</p>
                                                <button type="submit" class="btn btn-default">Export</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6 col-md-12">
                                <div class="box box-warning">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">Teleporter Import</h3>
                                    </div>
                                    <div class="box-body">
                                        <div class="row">
                                            <div class="col-lg-6 col-md-12">
                                                <label>Import ...</label>
                                                <div class="form-group">
                                                    <div class="checkbox">
                                                        <label><input type="checkbox" name="whitelist" value="true"
                                                                      checked>
                                                            Whitelist</label>
                                                    </div>
                                                    <div class="checkbox">
                                                        <label><input type="checkbox" name="blacklist" value="true"
                                                                      checked>
                                                            Blacklist (exact)</label>
                                                    </div>
                                                    <div class="checkbox">
                                                        <label><input type="checkbox" name="regexlist" value="true"
                                                                      checked>
                                                            Regex filters</label>
                                                    </div>
                                                    <div class="checkbox">
                                                        <label><input type="checkbox" name="auditlog" value="true"
                                                                      checked>
                                                            Audit log</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-6 col-md-12">
                                                <div class="form-group">
                                                    <label for="zip_file">File input</label>
                                                    <input type="file" name="zip_file" id="zip_file">
                                                    <p class="help-block">Upload only Pi-hole backup files.</p>
                                                    <button type="submit" class="btn btn-default" name="action"
                                                            value="in">Import
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <?php } else { ?>
                        <div class="col-lg-12">
                            <div class="box box-warning">
                                <div class="box-header with-border">
                                    <h3 class="box-title">Teleporter</h3>
                                </div>
                                <div class="box-body">
                                    <p>The PHP extension <code>Phar</code> is not loaded. Please ensure it is installed and loaded if you want to use the Pi-hole teleporter.</p>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>
                <!-- ######################################################### System admin ######################################################### -->
                <div id="sysadmin" class="tab-pane fade<?php if($tab === "sysadmin"){ ?> in active<?php } ?>">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="box">
                                <div class="box-header with-border">
                                    <h3 class="box-title">Network Information</h3>
                                </div>
                                <div class="box-body">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <table class="table table-striped table-bordered dt-responsive nowrap">
                                                <tbody>
                                                <tr>
                                                    <th scope="row">Pi-hole Ethernet Interface:</th>
                                                    <td><?php echo htmlentities($piHoleInterface); ?></td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">Pi-hole IPv4 address:</th>
                                                    <td><?php echo htmlentities($piHoleIPv4); ?></td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">Pi-hole IPv6 address:</th>
                                                    <td><?php echo htmlentities($piHoleIPv6); ?></td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">Pi-hole hostname:</th>
                                                    <td><?php echo htmlentities($hostname); ?></td>
                                                </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="box">
                                <div class="box-header with-border">
                                    <h3 class="box-title">FTL Information</h3>
                                </div>
                                <div class="box-body">
                                    <div class="row">
                                        <div class="col-lg-12">
                                            <?php
                                            if ($FTL) {
                                                function get_FTL_data($arg)
                                                {
                                                    global $FTLpid;
                                                    return trim(exec("ps -p " . $FTLpid . " -o " . $arg));
                                                }

                                                $FTLversion = exec("/usr/bin/pihole-FTL version");
                                            ?>
                                            <table class="table table-striped table-bordered dt-responsive nowrap">
                                                <tbody>
                                                    <tr>
                                                        <th scope="row">FTL version:</th>
                                                        <td><?php echo $FTLversion; ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th scope="row">Process identifier (PID):</th>
                                                        <td><?php echo $FTLpid; ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th scope="row">Time FTL started:</th>
                                                        <td><?php print_r(get_FTL_data("start")); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th scope="row">User / Group:</th>
                                                        <td><?php print_r(get_FTL_data("euser")); ?> / <?php print_r(get_FTL_data("egroup")); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th scope="row">Total CPU utilization:</th>
                                                        <td><?php print_r(get_FTL_data("%cpu")); ?>%</td>
                                                    </tr>
                                                    <tr>
                                                        <th scope="row">Memory utilization:</th>
                                                        <td><?php print_r(get_FTL_data("%mem")); ?>%</td>
                                                    </tr>
                                                    <tr>
                                                        <th scope="row">
                                                            <span title="Resident memory is the portion of memory occupied by a process that is held in main memory (RAM). The rest of the occupied memory exists in the swap space or file system.">Used memory:</span>
                                                        </th>
                                                        <td><?php echo formatSizeUnits(1e3 * floatval(get_FTL_data("rss"))); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th scope="row">
                                                            <span title="Size of the DNS domain cache">DNS cache size:</span>
                                                        </th>
                                                        <td id="cache-size">&nbsp;</td>
                                                    </tr>
                                                    <tr>
                                                        <th scope="row">
                                                            <span title="Number of cache insertions">DNS cache insertions:</span>
                                                        </th>
                                                        <td id="cache-inserted">&nbsp;</td>
                                                    </tr>
                                                    <tr>
                                                        <th scope="row">
                                                            <span title="Number of cache entries that had to be removed although they are not expired (increase cache size to reduce this number)">DNS cache evictions:</span>
                                                        </th>
                                                        <td id="cache-live-freed">&nbsp;</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                            See also our <a href="https://docs.pi-hole.net/ftldns/dns-cache/" target="_blank">DNS cache documentation</a>.
                                            <?php } else { ?>
                                            <div>The FTL service is offline!</div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="box box-warning">
                                <div class="box-header with-border">
                                    <h3 class="box-title">Danger Zone!</h3><br/>
                                </div>
                                <div class="box-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <?php if ($piHoleLogging) { ?>
                                                <button type="button" class="btn btn-warning confirm-disablelogging-noflush form-control">Disable query logging</button>
                                            <?php } else { ?>
                                                <form role="form" method="post">
                                                    <input type="hidden" name="action" value="Enable">
                                                    <input type="hidden" name="field" value="Logging">
                                                    <input type="hidden" name="token" value="<?php echo $token ?>">
                                                    <button type="submit" class="btn btn-success form-control">Enable query logging</button>
                                                </form>
                                            <?php } ?>
                                        </div>
                                        <p class="hidden-md hidden-lg"></p>
                                        <div class="col-md-4">
                                            <?php if ($piHoleLogging) { ?>
                                                <button type="button" class="btn btn-danger confirm-disablelogging form-control">Disable query logging and flush logs</button>
                                            <?php } ?>
                                        </div>
                                        <p class="hidden-md hidden-lg"></p>
                                        <div class="col-md-4">
                                            <button type="button" class="btn btn-warning confirm-restartdns form-control">Restart DNS resolver</button>
                                        </div>
                                    </div>
                                    <br/>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <button type="button" class="btn btn-danger confirm-flushlogs form-control">Flush logs</button>
                                        </div>
                                        <p class="hidden-md hidden-lg"></p>
                                        <div class="col-md-4">
                                            <button type="button" class="btn btn-danger confirm-poweroff form-control">Power off system</button>
                                        </div>
                                        <p class="hidden-md hidden-lg"></p>
                                        <div class="col-md-4">
                                            <button type="button" class="btn btn-danger confirm-reboot form-control">Restart system</button>
                                        </div>
                                    </div>

                                    <form role="form" method="post" id="flushlogsform">
                                        <input type="hidden" name="field" value="flushlogs">
                                        <input type="hidden" name="token" value="<?php echo $token ?>">
                                    </form>
                                    <form role="form" method="post" id="disablelogsform">
                                        <input type="hidden" name="field" value="Logging">
                                        <input type="hidden" name="action" value="Disable">
                                        <input type="hidden" name="token" value="<?php echo $token ?>">
                                    </form>
                                    <form role="form" method="post" id="disablelogsform-noflush">
                                        <input type="hidden" name="field" value="Logging">
                                        <input type="hidden" name="action" value="Disable-noflush">
                                        <input type="hidden" name="token" value="<?php echo $token ?>">
                                    </form>
                                    <form role="form" method="post" id="poweroffform">
                                        <input type="hidden" name="field" value="poweroff">
                                        <input type="hidden" name="token" value="<?php echo $token ?>">
                                    </form>
                                    <form role="form" method="post" id="rebootform">
                                        <input type="hidden" name="field" value="reboot">
                                        <input type="hidden" name="token" value="<?php echo $token ?>">
                                    </form>
                                    <form role="form" method="post" id="restartdnsform">
                                        <input type="hidden" name="field" value="restartdns">
                                        <input type="hidden" name="token" value="<?php echo $token ?>">
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="scripts/vendor/jquery.confirm.min.js"></script>
<script src="scripts/pi-hole/js/settings.js"></script>

<?php
require "scripts/pi-hole/php/footer.php";
?>
