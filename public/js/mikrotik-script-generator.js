window.updateMikroTikScript = function() {
    if (typeof window.configServerIp === 'undefined' || typeof window.configServerUrl === 'undefined' || typeof window.configRadiusSecret === 'undefined') {
        console.log("Config not ready yet");
        return;
    }
    
    var nasId = "";
    var selectors = [
        "input[name='nas_identifier']",
        "[name='nas_identifier']",
        "#nas_identifier",
        "input[id*='nas_identifier']",
        "[wire\\\\:model*='nas_identifier']"
    ];
    
    for (var i = 0; i < selectors.length; i++) {
        var el = document.querySelector(selectors[i]);
        if (el && el.value) {
            nasId = el.value;
            console.log("Found NAS ID via selector:", selectors[i], "value:", nasId);
            break;
        }
    }
    
    if (!nasId) {
        var allInputs = document.querySelectorAll("input[type='text']");
        for (var j = 0; j < allInputs.length; j++) {
            var inp = allInputs[j];
            if (inp.name && inp.name.includes("nas_identifier")) {
                nasId = inp.value || "";
                if (nasId) {
                    console.log("Found NAS ID via input scan:", nasId);
                    break;
                }
            }
        }
    }
    
    // Get RouterOS version - try multiple selectors
    var version = "v7";
    var versionSelectors = [
        "select[name='routeros_version']",
        "select[id*='routeros_version']",
        "[name='routeros_version']",
        "[id*='routeros_version']"
    ];
    
    for (var k = 0; k < versionSelectors.length; k++) {
        var vel = document.querySelector(versionSelectors[k]);
        if (vel && vel.value) {
            version = vel.value;
            console.log("Found version via selector:", versionSelectors[k], "value:", version);
            break;
        }
    }
    
    var includePool = document.getElementById("opt_pool") ? document.getElementById("opt_pool").checked : false;
    var includeProfile = document.getElementById("opt_profile") ? document.getElementById("opt_profile").checked : false;
    var includeServer = document.getElementById("opt_server") ? document.getElementById("opt_server").checked : false;
    var includeWalled = document.getElementById("opt_walled") ? document.getElementById("opt_walled").checked : true;
    var hotspotIpRaw = document.getElementById("hotspot_ip") ? document.getElementById("hotspot_ip").value : "192.168.88.1";
    // Remove CIDR notation if present (e.g., 192.168.100.1/24 -> 192.168.100.1)
    var hotspotIp = hotspotIpRaw.replace(/\/\d+$/, "");
    var hotspotNetwork = hotspotIp.split('.').slice(0, 3).join('.');
    var poolName = document.getElementById("pool_name") ? document.getElementById("pool_name").value : "hotspot-pool";
    var profileName = document.getElementById("profile_name") ? document.getElementById("profile_name").value : "luma-portal";
    var portalUrl = window.configServerUrl + "/portal";
    var portalUrlWithNasId = portalUrl + "?nas_id=" + encodeURIComponent(nasId);
    
    var hotspotCfg = document.getElementById("hotspot_config");
    if (hotspotCfg) hotspotCfg.classList.toggle("hidden", !includeProfile);
    
    // Show/hide v6 download section and update link
    var v6Download = document.getElementById("v6_download");
    var v6DownloadLink = document.getElementById("v6_download_link");
    if (v6Download) {
        var shouldShow = (version === "v6") && includeProfile;
        v6Download.classList.toggle("hidden", !shouldShow);
        console.log("v6_download visibility:", shouldShow, "version:", version, "includeProfile:", includeProfile);
        
        // Update download link with nas_id
        if (v6DownloadLink && nasId) {
            v6DownloadLink.href = "/mikrotik/hotspot-files?nas_id=" + encodeURIComponent(nasId);
            console.log("Updated download link:", v6DownloadLink.href);
        }
    }
    
    var lines = [];
    
    if (!nasId) {
        lines.push("# Ketik NAS Identifier di atas untuk generate script");
    } else {
        lines.push("# MikroTik Configuration - Luma Network");
        lines.push("# RouterOS Version: " + version);
        lines.push("");
        lines.push("# 1. System Identity");
        lines.push("/system identity");
        lines.push('set name="' + nasId + '"');
        lines.push("");
        lines.push("# 2. RADIUS Server");
        lines.push("/radius");
        lines.push("add service=hotspot address=" + window.configServerIp + " secret=" + window.configRadiusSecret + " authentication-port=1812 accounting-port=1813");
        lines.push("");
        
        if (includePool) {
            lines.push("# 3. Address Pool");
            lines.push("/ip pool");
            lines.push("add name=" + poolName + " ranges=" + hotspotNetwork + ".10-" + hotspotNetwork + ".254");
            lines.push("");
        }
        
        if (includeProfile) {
            if (version === "v7") {
                lines.push("# 4. Hotspot Setup (RouterOS v7)");
                lines.push("");
                lines.push("# 4a. Hotspot Profile");
                lines.push("/ip hotspot profile");
                lines.push("add name=" + profileName + " hotspot-address=" + hotspotIp + " login-by=http-pap,http-chap,cookie http-cookie-lifetime=1d use-radius=yes radius-accounting=yes radius-interim-update=5m http-redirect=yes redirect-url=" + portalUrlWithNasId);
                lines.push("");
                lines.push("# 4b. DNS - Redirect all DNS queries to MikroTik (required for captive portal detection)");
                lines.push("/ip dns");
                lines.push("set allow-remote-requests=yes cache-size=4096");
                lines.push("");
                lines.push("# 4c. NAT - Redirect DNS to MikroTik");
                lines.push("/ip firewall nat");
                lines.push("add chain=dstnat protocol=udp dst-port=53 action=redirect to-ports=53 comment=\"DNS Redirect\"");
                lines.push("add chain=dstnat protocol=tcp dst-port=53 action=redirect to-ports=53 comment=\"DNS Redirect TCP\"");
                lines.push("");
                lines.push("# 4d. NAT - Redirect HTTP to hotspot for captive portal (unauthenticated users)");
                lines.push("add chain=pre-hotspot protocol=tcp dst-port=80 hotspot=auth action=return comment=\"Skip auth users\"");
                lines.push("add chain=dstnat protocol=tcp dst-port=80 src-address-list=!hotspot-auth action=dst-nat to-addresses=" + hotspotIp + " to-ports=80 comment=\"HTTP to hotspot\"");
            } else {
                lines.push("# 4. Hotspot Setup (RouterOS v6 - requires custom hotspot files)");
                lines.push("/ip hotspot profile");
                lines.push("add name=" + profileName + " hotspot-address=" + hotspotIp + " login-by=http-pap,http-chap,cookie http-cookie-lifetime=1d use-radius=yes radius-accounting=yes radius-interim-update=5m");
                lines.push("");
                lines.push("# Note: For RouterOS v6, upload hotspot files (login.html, redirect.html) to /hotspot folder");
                lines.push("# These files redirect users to the captive portal with nas_id parameter");
                lines.push("");
                lines.push("# DNS - Redirect all DNS queries to MikroTik (required for captive portal detection)");
                lines.push("/ip dns");
                lines.push("set allow-remote-requests=yes cache-size=4096");
                lines.push("");
                lines.push("# NAT - Redirect DNS to MikroTik");
                lines.push("/ip firewall nat");
                lines.push("add chain=dstnat protocol=udp dst-port=53 action=redirect to-ports=53 comment=\"DNS Redirect\"");
                lines.push("add chain=dstnat protocol=tcp dst-port=53 action=redirect to-ports=53 comment=\"DNS Redirect TCP\"");
            }
            lines.push("");
        }
        
        if (includeServer) {
            lines.push("# 5. Hotspot Server");
            lines.push("/ip hotspot");
            lines.push("add name=" + profileName + "-server interface=bridge-lan address-pool=" + poolName + " profile=" + profileName + " disabled=no");
            lines.push("");
        }
        
        if (includeWalled) {
            lines.push("# " + (version === "v7" ? "5" : "5") + ". Walled Garden (Allow access to portal and CNA detection servers)");
            lines.push("/ip hotspot walled-garden ip");
            lines.push("add dst-address=" + window.configServerIp + " action=accept comment=\"Luma Portal Server\"");
            lines.push("add dst-port=53 protocol=udp action=accept comment=\"DNS\"");
            lines.push("add dst-port=53 protocol=tcp action=accept comment=\"DNS TCP\"");
            lines.push("add dst-host=*.lumanetwork.id action=accept comment=\"Luma Domain\"");
            lines.push("add dst-host=captive.apple.com action=accept comment=\"iOS CNA Detection\"");
            lines.push("add dst-host=*.apple.com action=accept comment=\"Apple Services\"");
            lines.push("add dst-host=connectivitycheck.gstatic.com action=accept comment=\"Android CNA Detection\"");
            lines.push("add dst-host=*.google.com action=accept comment=\"Google Services\"");
            lines.push("add dst-host=*.googleapis.com action=accept comment=\"Google APIs\"");
            lines.push("add dst-host=*.facebook.com action=accept comment=\"Facebook\"");
            lines.push("add dst-host=*.whatsapp.com action=accept comment=\"WhatsApp\"");
            lines.push("add dst-host=*.whatsapp.net action=accept comment=\"WhatsApp CDN\"");
            lines.push("");
        }
    }
    
    var box = document.getElementById("mikrotik-script-box");
    if (box) box.textContent = lines.join("\n");
};

window.copyMikroTikScript = function(btn) {
    var script = document.getElementById("mikrotik-script-box").innerText;
    if (!script || script.includes("Ketik NAS Identifier")) {
        alert("Isi NAS Identifier terlebih dahulu");
        return;
    }
    
    var copyBtn = btn;
    var originalText = copyBtn ? copyBtn.textContent : "Copy Script";
    
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(script).then(function() {
            if (copyBtn) {
                copyBtn.textContent = "Copied!";
                copyBtn.style.backgroundColor = "#16a34a";
                setTimeout(function() {
                    copyBtn.textContent = originalText;
                    copyBtn.style.backgroundColor = "";
                }, 2000);
            }
        }).catch(function() {
            fallbackCopy(script, copyBtn, originalText);
        });
    } else {
        fallbackCopy(script, copyBtn, originalText);
    }
};

function fallbackCopy(text, btn, originalText) {
    var ta = document.createElement("textarea");
    ta.value = text;
    ta.style.position = "fixed";
    ta.style.opacity = "0";
    document.body.appendChild(ta);
    ta.select();
    document.execCommand("copy");
    document.body.removeChild(ta);
    if (btn) {
        btn.textContent = "Copied!";
        btn.style.backgroundColor = "#16a34a";
        setTimeout(function() {
            btn.textContent = originalText;
            btn.style.backgroundColor = "";
        }, 2000);
    }
}

document.addEventListener("change", function(e) {
    if (e.target && (e.target.id === "opt_pool" || e.target.id === "opt_profile" || e.target.id === "opt_server" || e.target.id === "opt_walled" || e.target.id === "hotspot_ip" || e.target.id === "pool_name" || e.target.id === "profile_name")) {
        window.updateMikroTikScript();
    }
    // Handle RouterOS version change
    if (e.target && (e.target.name === "routeros_version" || e.target.id && e.target.id.includes("routeros_version"))) {
        console.log("RouterOS version changed:", e.target.value);
        window.updateMikroTikScript();
    }
});

document.addEventListener("input", function(e) {
    if (e.target && e.target.name && e.target.name.includes && e.target.name.includes("nas_identifier")) {
        window.updateMikroTikScript();
    }
});

document.addEventListener("livewire:update", function() {
    setTimeout(window.updateMikroTikScript, 100);
});

document.addEventListener("alpine:initialised", function() {
    setTimeout(window.updateMikroTikScript, 200);
});

(function() {
    function tryUpdate() {
        if (typeof window.configServerIp !== 'undefined' && typeof window.configServerUrl !== 'undefined') {
            window.updateMikroTikScript();
        }
    }
    
    var attempts = 0;
    var maxAttempts = 50;
    
    function checkExistingValue() {
        var found = false;
        var selectors = [
            "input[name='nas_identifier']",
            "[name='nas_identifier']",
            "#nas_identifier"
        ];
        
        for (var i = 0; i < selectors.length; i++) {
            var el = document.querySelector(selectors[i]);
            if (el && el.value && el.value.trim() !== "") {
                found = true;
                console.log("Poll found NAS ID:", el.value);
                break;
            }
        }
        
        if (found) {
            tryUpdate();
            return;
        }
        
        attempts++;
        if (attempts < maxAttempts) {
            setTimeout(checkExistingValue, 200);
        }
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { setTimeout(checkExistingValue, 500); });
    } else {
        setTimeout(checkExistingValue, 500);
    }
    
    window.addEventListener('load', function() { setTimeout(checkExistingValue, 800); });
    setTimeout(function() { checkExistingValue(); }, 1500);
    setTimeout(function() { checkExistingValue(); }, 3000);
})();