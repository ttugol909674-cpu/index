cd /home/taller/Desktop/tgs
echo "=== URL antes de romper el tunel ==="
cat .tunel.url
echo
echo "=== se mata el proceso del tunel ==="
bash scripts/tunel.sh detener
sleep 2
curl -s -o /dev/null -w "  sitio publico: HTTP %{http_code} (000 = caido)\n" --max-time 15 "$(cat .tunel.url)"
echo
echo "=== el vigilante lo levanta solo ==="
bash scripts/tunel_vigilante.sh vigilar
sleep 3
echo "  URL nueva: $(cat .tunel.url)"
curl -s -o /dev/null -w "  sitio publico: HTTP %{http_code}\n" --max-time 25 "$(cat .tunel.url)"
echo
echo "=== el archivo del sitio quedo actualizado ==="
head -2 url-publica.txt
