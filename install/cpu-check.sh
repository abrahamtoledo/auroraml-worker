#!/bin/bash

apt install -y xinetd

prot_name="cpu-check"
cpu_check_server=/usr/local/bin/${prot_name}

echo "#!/bin/bash
LMAX=90

load=\$(uptime | sed 's/.*average\s*:\s*\(.*\)/\1/g' | sed 's/,\s/ /g' | sed 's/,/./g' | cut -d' ' -f 1)
cpus=\$(nproc)

weight=$(echo \"100-\$load/\$cpus\*100\" | bc -l | cut -d '.' -f1)
[[ \$weight -lt 10 ]] && weight=10

echo \$weight

exit 0
" > "${cpu_check_server}"
chmod +x "${cpu_check_server}"

[[ $( cat /etc/services | grep -i "${prot_name}" | wc -l ) -gt 0 ]] || \
    echo "${prot_name}      9707/tcp        #cpu check for haproxy" >> /etc/services

echo '# default: on
# description: haproxy-agent-check
service cpu-check
{
        disable         = no
        flags           = REUSE
        socket_type     = stream
        port            = 9707
        wait            = no
        user            = nobody
        server          = /usr/local/bin/cpu-check
        log_on_failure  += USERID
        only_from       = 192.168.0.0/24 127.0.0.0/8
        per_source      = UNLIMITED
}

' > /etc/xinet.d/${prot_name}

chmod +x /etc/xinet.d/${prot_name}

systemctl restart xinetd
