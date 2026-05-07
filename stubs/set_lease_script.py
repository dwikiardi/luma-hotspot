import sys
from routeros_api import RouterOsApiPool

mikrotik_ip = sys.argv[1] if len(sys.argv) > 1 else "10.0.70.4"

ros_script = ":log info (\"LumaDHCP: bound=\" . $" + "leaseBound . \" mac=\" . $" + "leaseActMAC . \" ip=\" . $" + "leaseActIP . \" host=\" . $" + "hostName)\r\n"
ros_script += ":if ($" + "leaseBound = \"1\") do={\r\n"
ros_script += "  :local payload (\"mac=\" . $" + "leaseActMAC . \"&ip=\" . $" + "leaseActIP . \"&host=\" . $" + "hostName . \"&server=\" . $" + "leaseServerName)\r\n"
ros_script += "  /tool fetch url=\"http://103.137.140.6:8081/api/dhcp-hook\" http-method=post http-data=$" + "payload\r\n"
ros_script += "}\r\n"

pool = RouterOsApiPool(mikrotik_ip, username="admin", password="", plaintext_login=True, use_ssl=False)
api = pool.get_api()

scr = api.get_resource("/system/script")
script_id = None
for s in scr.get():
    if s.get("name") == "luma-dhcp-hook":
        script_id = s.get(".id") or s.get("id")
        break

if script_id:
    scr.call("set", {".id": script_id, "source": ros_script, "policy": "read,write,test,reboot"})
else:
    scr.call("add", {"name": "luma-dhcp-hook", "source": ros_script, "policy": "read,write,test,reboot"})

ds = api.get_resource("/ip/dhcp-server")
for s in ds.get():
    rid = s.get(".id") or s.get("id")
    ds.call("set", {".id": rid, "lease-script": "luma-dhcp-hook"})

pool.disconnect()
