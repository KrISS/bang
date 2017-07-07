touch bang.csv
wget -q https://duckduckgo.com/bang_lite.html -O bang.html
for i in $(\grep '^!' bang.html | cut -d ' ' -f 1 | sort -u)
do
    nb=$(grep -c "\"$i\"" bang.csv)
    if [ "$nb" -eq "0" ]
    then
        wget -q 'https://duckduckgo.com/?q='$i -O output.html
        url=$(php -r '$s=file_get_contents("output.html");preg_match("/uddg=([^'"'"']+)/",$s,$matches);if (!isset($matches[1])) { preg_match("/url=([^'"'"']+)/",$s,$matches); } echo(urldecode($matches[1]));')
        wget -q 'https://duckduckgo.com/?q=ddg_bang%20'$i -O output.html
        pattern=$(php -r '$s=file_get_contents("output.html");preg_match("/uddg=([^'"'"']+)/",$s,$matches); if (!isset($matches[1])) { preg_match("/url=([^'"'"']+)/",$s,$matches); } echo(urldecode($matches[1]));')
        echo $i $url $pattern;
        sleep 0.24
    fi
done > bang.txt
rm bang.html
rm output.html

\grep -e "^[^ ]* [^ ]* [^ ]*$" bang.txt | sed -e 's:":\":g' | sed -e 's:^:":g' | sed -e 's:$:":g' | sed -e 's: :";":g' >> bang.csv
rm bang.txt
sed -i -e 's:ddg_bang:kriss_bang:g' bang.csv
