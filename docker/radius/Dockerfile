FROM freeradius/freeradius-server:latest

COPY raddb/clients.conf /etc/freeradius/clients.conf
COPY raddb/mods-enabled/sql /etc/freeradius/mods-enabled/sql
# Rest module disabled - using scheduler sync instead
# COPY raddb/mods-enabled/rest /etc/freeradius/mods-enabled/rest
# Use image default sites-enabled/default
# COPY raddb/sites-enabled/default /etc/freeradius/sites-enabled/default

RUN sed -i 's/^.*require_message_authenticator = auto/	require_message_authenticator = no/' /etc/freeradius/radiusd.conf