<?php

// Never boot the application or connect to a database here.
fwrite(STDERR, "Este generador antiguo fue retirado: no crea ni borra cuentas.\nUse testing:single-demo únicamente con APP_ENV=testing y una base sas_san_test vacía.\n");
exit(1);
