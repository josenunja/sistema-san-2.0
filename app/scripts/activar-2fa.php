<?php

// MFA must be enrolled personally, never set to a shared secret by a script.
fwrite(STDERR, "Esta herramienta fue retirada. Configure MFA desde Seguridad con su cuenta.\nPara recuperar acceso local use recuperar-acceso-local.ps1.\n");
exit(1);
