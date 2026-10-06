#!/usr/bin/env bash
# The offline messages of a grid, on the grid of the scenario: what the OfflineMessageModule of a simulator asks offline.php
# of the helpers (the URL of its config is .../helpers/offline.php, it adds /SaveMessage/ and /RetrieveMessages/ and sends the
# XML of the message as OpenSimulator serializes it), the mails of the helpers for those who ask for them in their viewer, and
# what a grid does for a message another grid sends to one of its users who is offline. Sourced by package-scenario.sh
# (STOP_AFTER=offline stops the scenario after it), in the test container.

[[ $(type -t check) == function ]] || source /test/scenario-lib.sh

db=testgrid_robust
sql() { mysql -BN "$db" -e "$1"; }
helpers=http://127.0.0.1:8090/helpers
zero=00000000-0000-0000-0000-000000000000
account() { sql "SELECT PrincipalID FROM UserAccounts WHERE FirstName='$1' AND LastName='$2'"; }
xml() { php /test/offline/xml-probe.php - "$1"; }

# A message as OpenSimulator serializes a GridInstantMessage
im() { # from, from name, to, dialog, message
    cat <<XML
<?xml version="1.0" encoding="utf-8"?>
<GridInstantMessage xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <fromAgentID>$1</fromAgentID>
  <fromAgentName>$2</fromAgentName>
  <toAgentID>$3</toAgentID>
  <dialog>$4</dialog>
  <fromGroup>false</fromGroup>
  <message>$5</message>
  <imSessionID>$zero</imSessionID>
  <offline>0</offline>
  <Position><X>0</X><Y>0</Y><Z>0</Z></Position>
  <binaryBucket>AA==</binaryBucket>
  <ParentEstateID>0</ParentEstateID>
  <RegionID>$zero</RegionID>
  <timestamp>1791266000</timestamp>
</GridInstantMessage>
XML
}
post() { curl -s -m 60 -X POST -H 'Content-Type: text/xml' --data-binary @- "$1"; }
save() { im "$@" | post "$helpers/offline.php/SaveMessage/"; }
retrieve() { # principal
    printf '<?xml version="1.0" encoding="utf-8"?><UUID xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><Guid>%s</Guid></UUID>' "$1" | post "$helpers/offline.php/RetrieveMessages/"
}
mails() { ls /tmp/mails/*.eml 2>/dev/null | wc -l | tr -d ' '; }
last_mail() { ls -t /tmp/mails/*.eml | head -1; }

recipient=$(account Bulk One)
sender=$(account Bulk Two)
other=$(account Later User)

ts "offline: the site of the helpers, and a mail box"
pkill -f 'php .*-S 127.0.0.1:8090' 2>/dev/null || true
mkdir -p /tmp/mails
rm -f /tmp/mails/*
sql "DELETE FROM im_offline" 2>/dev/null || true
(cd /var/www/html && OPENSIM_GRID=testgrid PHP_CLI_SERVER_WORKERS=4 setsid -f php -d sendmail_path=/test/offline/mail-fake -S 127.0.0.1:8090 index.php >/tmp/php-offline.log 2>&1)
wait_check 20 "the site of the helpers answers" "[ \"\$(curl -s -o /dev/null -w '%{http_code}' $helpers/motd.php)\" = 200 ]"
check "the grid has the table of the offline messages" "[ \"\$(sql \"SHOW TABLES LIKE 'im_offline'\")\" = im_offline ]"
sql "CREATE TABLE IF NOT EXISTS usersettings (useruuid varchar(36) NOT NULL, imviaemail enum('true','false') NOT NULL, visible enum('true','false') NOT NULL, email varchar(254) NOT NULL, PRIMARY KEY (useruuid))"
sql "DELETE FROM usersettings"

ts "offline: a simulator stores a message for a user who is not there"
check "its URL, with the method after it, reaches the script" "[ \"\$(save $sender 'Bulk Two' $recipient 0 'Hello Bulk One' | xml '/boolean')\" = true ]"
check "the message is kept for its recipient, with its sender" "[ \"\$(sql \"SELECT CONCAT(FromID, '|', PrincipalID) FROM im_offline WHERE PrincipalID='$recipient'\")\" = '$sender|$recipient' ]"
save $sender 'Bulk Two' $recipient 0 'A second message, &lt;b&gt;with&lt;/b&gt; markup &amp; "quotes", and a second line:
Line two' >/dev/null
save $sender 'Bulk Two' $other 0 'Not for Bulk One' >/dev/null

ts "offline: the user comes back, the simulator asks for the messages"
# The answer is kept in a file, to be read by the checks
reply=/tmp/offline-reply.xml
retrieve "$recipient" >$reply
messages() { php /test/offline/xml-probe.php $reply "$1"; }
check "the answer is the list of the messages as OpenSimulator reads it" "[ \"\$(messages 'count(/ArrayOfGridInstantMessage/GridInstantMessage)')\" = 2 ]"
check "the messages of the user, in the order they came, not the ones of another" "[ \"\$(messages '/ArrayOfGridInstantMessage/GridInstantMessage[1]/message')\" = 'Hello Bulk One' ] && ! grep -q 'Not for Bulk One' $reply"
check "each message has what the simulator needs of it" "[ \"\$(messages '/ArrayOfGridInstantMessage/GridInstantMessage[1]/fromAgentName')\" = 'Bulk Two' ] &&
    [ \"\$(messages '/ArrayOfGridInstantMessage/GridInstantMessage[1]/fromAgentID')\" = '$sender' ] && [ \"\$(messages '/ArrayOfGridInstantMessage/GridInstantMessage[1]/dialog')\" = 0 ]"
check "the markup, the quotes and the lines of a message are kept" "messages '/ArrayOfGridInstantMessage/GridInstantMessage[2]/message' | grep -q '<b>with</b> markup & \"quotes\"' &&
    messages '/ArrayOfGridInstantMessage/GridInstantMessage[2]/message' | grep -q '^Line two'"
check "a message is delivered once: nothing more for the user, the message of the other user is still there" "[ \"\$(retrieve $recipient | xml 'count(/ArrayOfGridInstantMessage/GridInstantMessage)')\" = 0 ] &&
    [ \"\$(sql \"SELECT COUNT(*) FROM im_offline WHERE PrincipalID='$other'\")\" = 1 ]"
check "a user nobody wrote to has an empty list" "[ \"\$(retrieve 99999999-9999-4999-8999-999999999999 | xml 'count(/ArrayOfGridInstantMessage/GridInstantMessage)')\" = 0 ]"
check "a method it does not know is refused" "[ \"\$(im $sender X $recipient 0 x | post $helpers/offline.php/Nope/ | grep -c 'not implemented')\" = 1 ]"
sql "DELETE FROM im_offline"

ts "offline: the mail of those who ask for it in their viewer"
check "nobody asked: no mail" "save $sender 'Bulk Two' $recipient 0 'Nobody asked' >/dev/null; sleep 4; [ \"\$(mails)\" = 0 ]"
sql "DELETE FROM im_offline"
sql "INSERT INTO usersettings VALUES ('$recipient', 'true', 'true', 'bulk.one@example.test')"
save $sender 'Bulk Two' $recipient 0 'Subject of the mail|Body of the mail' >/dev/null
wait_check 30 "a message for a user who asked is mailed" "[ \"\$(mails)\" = 1 ]"
check "the mail goes to the address of the user, from the sender, with the subject of the message and its body" "m=\$(last_mail); grep -qi '^To: bulk.one@example.test' \$m && grep -qi '^Subject: Message from Bulk Two' \$m &&
    grep -qi '^From: Bulk Two <no-reply@' \$m && grep -q 'Body of the mail' \$m && grep -q 'Subject of the mail' \$m"
check "the message is kept in-world as well" "[ \"\$(sql \"SELECT COUNT(*) FROM im_offline WHERE PrincipalID='$recipient'\")\" = 1 ]"
rm -f /tmp/mails/*
save $sender 'Bulk Two' $recipient 32 'Weekly news|The group notice' >/dev/null
wait_check 30 "a group notice is mailed" "[ \"\$(mails)\" = 1 ]"
check "a group notice has its own subject" "grep -qi '^Subject: Group notice: Weekly news' \$(last_mail)"
for entry in '3|Group invitation from Bulk Two' '4|Inventory offer from Bulk Two' '38|Friendship offer from Bulk Two'; do
    rm -f /tmp/mails/*
    save $sender 'Bulk Two' $recipient "${entry%%|*}" 'Would you like' >/dev/null
    wait_check 30 "dialog ${entry%%|*} is mailed with the subject of its kind: ${entry#*|}" "[ \"\$(mails)\" = 1 ] && grep -qi \"^Subject: ${entry#*|}\" \$(last_mail)"
done
sql "UPDATE usersettings SET imviaemail = 'false' WHERE useruuid = '$recipient'"
rm -f /tmp/mails/*
save $sender 'Bulk Two' $recipient 0 'Mail switched off' >/dev/null
sleep 4
check "a user who switched the mails off in the viewer gets none" "[ \"\$(mails)\" = 0 ]"
sql "UPDATE usersettings SET imviaemail = 'true', email = '' WHERE useruuid = '$recipient'"
save $sender 'Bulk Two' $recipient 0 'No address' >/dev/null
sleep 4
check "nor a user who has no address" "[ \"\$(mails)\" = 0 ]"
sql "DELETE FROM usersettings"
sql "DELETE FROM im_offline"

# A user of another grid writes to a user of this one who is not there: the other grid sends it to the gatekeeper of this
# grid (XML-RPC grid_instant_message), which stores it itself, with the offline service of Robust, in the same table. Its
# answer is whether the message was delivered, not whether it was kept: FALSE, and the other grid cannot tell the sender
ts "offline: a message from another grid"
hg_im() { # to, message
    curl -s -m 60 -H 'Content-Type: text/xml' --data-binary @- http://127.0.0.1:8002/ <<XML
<?xml version="1.0"?><methodCall><methodName>grid_instant_message</methodName><params><param><value><struct>
<member><name>from_agent_id</name><value><string>aaaaaaaa-1111-4111-8111-000000000001</string></value></member>
<member><name>to_agent_id</name><value><string>$1</string></value></member>
<member><name>im_session_id</name><value><string>$zero</string></value></member>
<member><name>timestamp</name><value><string>1791266000</string></value></member>
<member><name>from_agent_name</name><value><string>Visitor Foreign @other.example.test:8002</string></value></member>
<member><name>message</name><value><string>$2</string></value></member>
<member><name>dialog</name><value><string>AA==</string></value></member>
<member><name>from_group</name><value><string>FALSE</string></value></member>
<member><name>offline</name><value><string>AA==</string></value></member>
<member><name>parent_estate_id</name><value><string>0</string></value></member>
<member><name>position_x</name><value><string>0</string></value></member>
<member><name>position_y</name><value><string>0</string></value></member>
<member><name>position_z</name><value><string>0</string></value></member>
<member><name>region_id</name><value><string>$zero</string></value></member>
<member><name>binary_bucket</name><value><string>AA==</string></value></member>
</struct></value></param></params></methodCall>
XML
}
hg_im "$recipient" "Greetings from another grid" >$reply
check "the gatekeeper keeps it for the user, with the name of its sender" "[ \"\$(sql \"SELECT COUNT(*) FROM im_offline WHERE PrincipalID='$recipient'\")\" = 1 ] && sql \"SELECT Message FROM im_offline WHERE PrincipalID='$recipient'\" | grep -q 'Visitor Foreign'"
check "the helpers give it to the simulator when the user comes back, as one of their own messages" "[ \"\$(retrieve $recipient | xml '/ArrayOfGridInstantMessage/GridInstantMessage/message')\" = 'Greetings from another grid' ]"
sql "DELETE FROM im_offline"

pkill -f 'php .*-S 127.0.0.1:8090' 2>/dev/null || true
