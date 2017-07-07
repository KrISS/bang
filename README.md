# KrISS bang
A simple and smart (or stupid) [bang](https://duckduckgo.com/bang) manager

A demo is available on [tontof.net](https://tontof.net/bang).

Installation
============
* If you just want to use KrISS bang, download [kriss_bang.php](https://raw.github.com/kriss/bang/master/kriss_bang.php) file and upload it on your server. Enjoy !

To remove the kriss_bang.php file, you can add a ```.htaccess``` file
```
DirectoryIndex kriss_bang.php
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(.*)$ kriss_bang.php [QSA,L]
</IfModule>
```

* If you want to look at the source, look at the src directory. This code use the [KrISS mvvm](https://github.com/kriss/mvvm) project. To compile the kriss_bang.php file you need to also clone KrISS mvvm:

```bash
git clone https://github.com/kriss/mvvm
git clone https://github.com/kriss/bang
cd bang/bin
bash compile.sh
```

More information here: [KrISS bang](http://tontof.net/kriss/bang).

Manage bang
===========
You can create your own bang using ```!``` sign, a default ```url``` if the search is empty and a search ```pattern``` using the ```kriss_bang``` keyword to replace by your search

For example:
------------
* bang: ```!duckduckgo```
* url: ```https://duckduckgo.com```
* pattern: ```https://duckduckgo.com/?q=kriss_bang```

Alternatively, you can upload a csv file. [Duckduckgo bangs](https://raw.github.com/kriss/bang/master/bin/bang.csv) are available to init your bangs!

Licence
=======
Copyleft (ɔ) - Tontof - http://tontof.net

Use KrISS bang at your own risk.

[Free software means users have the four essential freedoms](http://www.gnu.org/philosophy/philosophy.html):
* to run the program
* to study and change the program in source code form
* to redistribute exact copies, and
* to distribute modified versions.
