#!/bin/bash
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh
ADMIN="admin@bluetech.com"
PASS="$TGS_PASS_ADMIN"
JAR=/tmp/cookies_t.txt
rm -f $JAR
tgs_login $BASE $JAR "$ADMIN" "$PASS"

for q in "Maldonado" "Centro" "TGS" "maldonado@tgs.com.uy" "Av. Italia" "S%"; do
    H=$(curl -s -c $JAR -b $JAR --get --data-urlencode "q=$q" "$BASE/administrar_talleres.php")
    N=$(echo "$H" | grep -c "card-custom shadow")
    echo "q='$q' -> tarjetas=$N"
done
rm -f $JAR
echo FIN
