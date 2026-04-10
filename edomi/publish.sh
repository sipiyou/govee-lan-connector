php compile.php

sed -i \
  -e "s|^//require('wrapper.php');|require('wrapper.php');|" \
  -e "s|^require(dirname(__FILE__).*incl_lbsexec.php.*|//&|" \
  19002760_lbs.php

scp 19002760_lbs.php root@edomi.home.local:~/edomi/tests/govee
#scp wrapper.php root@edomi.home.local:~/edomi/tests/govee
