FROM freeradius/freeradius-server:latest

COPY raddb/clients.conf /etc/freeradius/clients.conf
COPY raddb/mods-enabled/sql /etc/freeradius/mods-enabled/sql

RUN sed -i 's/^.*require_message_authenticator = auto/	require_message_authenticator = no/' /etc/freeradius/radiusd.conf