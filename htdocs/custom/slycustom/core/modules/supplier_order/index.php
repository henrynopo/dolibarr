<?php
// Directory guard: block direct web access and directory listing.
// (Dolibarr module convention — see modulebuilder template.)
http_response_code(403);
exit;
