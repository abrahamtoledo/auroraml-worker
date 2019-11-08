# auroraml-worker
Worker servers for AuroraSuite. A worker server receives incomming requests from the master server, 
process it and reply to the client

__IMPORTANT NOTE: This document is not completed. Work in progress__
Contact me if you encounter any problem with installation. All contributions are welcome.

## Prerequisites
You must have:
* A postfix server from wich to send replies
* A mysql database (a creation script for the database will be provided soon)

## Installation

Run installation script:
```bash
pushd install && ./install.sh

```
You will need to provide `database` information, `postfix` information, domain name of the sender, and some other data.

## About Master Server
A [Master Server](https://github.com/abrahamtoledo/auroraml-master/) is also needed, the master server contains the 
frontend webpage, admin panel, syslogs, HAProxy load balancer.

Althoug ideally you would have all the services in diferent servers, it is also possible to install everything on a single 
server (posibly with minor modifications to the code).
