echo '<!php' > ../kriss_bang.php
echo '#### KrISS bang: a simple and smart (or stupid) bang manager' >> ../kriss_bang.php
echo '#### Copyleft (ɔ) - Tontof - http://tontof.net' >> ../kriss_bang.php
echo '#### use KrISS bang at your own risk' >> ../kriss_bang.php

cat ../../mvvm/Kriss/Core/Model/ArrayModelTrait.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/Model/ArrayModel.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/ViewModel/ViewModel.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/ViewModel/ViewModelTrait.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/ViewModel/FormViewModel.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/Router/RequestRouter.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/Router/RouterTrait.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/Router/Router.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/Response/RedirectResponse.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/Response/Response.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/Response/UnauthorizedResponse.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/Response/ExceptionResponse.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/Response/ViewControllerResponse.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/Response/BasicUnauthorizedResponse.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/Response/ResponseTrait.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/View/View.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/View/FormView.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/View/ViewTrait.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/Session/Session.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/Container/Container.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/FormAction/RemoveFormAction.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/FormAction/FormActionTrait.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/FormAction/PersistFormAction.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/Request/Request.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/Controller/ListControllerTrait.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/Controller/FormControllerTrait.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/Controller/FormController.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/Controller/FormListController.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/Controller/ListController.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/Form/Form.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/Auth/BasicAuthentication.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/Auth/UserProvider.php >> ../kriss_bang.php
# cat ../../mvvm/Kriss/Core/Auth/Authorization.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/Auth/SessionAuthentication.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/Auth/PrivateRequestAuthorization.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/Auth/ProtectedRequestAuthorization.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/Auth/HashPassword.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/App/App.php >> ../kriss_bang.php
cat ../../mvvm/Kriss/Core/Validator/Validator.php >> ../kriss_bang.php

head -n -3 ../src/index.php >> ../kriss_bang.php
cat ../../mvvm/plugins/auth.php >> ../kriss_bang.php
cat ../../mvvm/plugins/authSession.php >> ../kriss_bang.php
cat ../../mvvm/plugins/authBasic.php >> ../kriss_bang.php
cat ../../mvvm/plugins/modelArray.php >> ../kriss_bang.php
cat ../../mvvm/plugins/routerAuto.php >> ../kriss_bang.php
cat ../../mvvm/plugins/responseException.php >> ../kriss_bang.php
cat ../../mvvm/plugins/config.php >> ../kriss_bang.php
tail -n 3 ../src/index.php >> ../kriss_bang.php

sed -i -e 's:<?php::g' -e 's:<!php:<?php:g' ../kriss_bang.php
sed -i -e 's:^namespace.*::g' ../kriss_bang.php
sed -i -e 's:^use.*::g' ../kriss_bang.php
sed -i -e 's: implements .*:{:g' ../kriss_bang.php
sed -i -e 's#://#dotslashslash#g' ../kriss_bang.php
sed -i -e 's#"//#quoteslashslash#g' ../kriss_bang.php
sed -i -e 's#//.*##g' ../kriss_bang.php
sed -i -e 's#dotslashslash#://#g' ../kriss_bang.php
sed -i -e 's#quoteslashslash#"//#g' ../kriss_bang.php
sed -i -e 's:include.*::g' ../kriss_bang.php
sed -i -e 's:####://:g' ../kriss_bang.php

sed -i -e 's/AuthenticationInterface::authenticationSuccess/1/g' ../kriss_bang.php
sed -i -e 's/AuthenticationInterface::alreadyAuthenticated/2/g' ../kriss_bang.php
sed -i -e 's/AuthenticationInterface::unknownUser/3/g' ../kriss_bang.php
sed -i -e 's/AuthenticationInterface::wrongPassword/4/g' ../kriss_bang.php
sed -i -e 's/AuthenticationInterface::wrongCredentials/5/g' ../kriss_bang.php

sed -i -e 's/[a-zA-Z]*Interface //g' ../kriss_bang.php
sed -i -e 's/Kriss\\\\Core\\\\.*\\\\//g' ../kriss_bang.php

favicon=$(base64 ../src/inc/favicon.ico)
sed -i -e "s:base64_encode(file_get_contents('inc/favicon.ico'));:<<<base64\n$(echo ${favicon})\nbase64;:g" ../kriss_bang.php

#optimize size
sed -i '/^[ ]*$/d' ../kriss_bang.php
sed -i 's/    / /g' ../kriss_bang.php
sed -i 's/) {/){/g' ../kriss_bang.php