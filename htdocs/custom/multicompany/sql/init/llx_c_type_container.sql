-- Copyright (C) 2009-2021 Regis Houssin  <regis.houssin@inodbox.com>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program; if not, write to the Free Software
-- Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
--
--

--
-- Ne pas placer de commentaire en fin de ligne, ce fichier est parsé lors
-- de l'install et tous les sigles '--' sont supprimés.
--


--
-- llx_c_socialnetworks
--
INSERT INTO llx_c_type_container (entity, code, label, module, active) VALUES (__ENTITY__, 'page',     'Page',     'system', 1);
INSERT INTO llx_c_type_container (entity, code, label, module, active) VALUES (__ENTITY__, 'banner',   'Banner',   'system', 1);
INSERT INTO llx_c_type_container (entity, code, label, module, active) VALUES (__ENTITY__, 'blogpost', 'BlogPost', 'system', 1);
INSERT INTO llx_c_type_container (entity, code, label, module, active) VALUES (__ENTITY__, 'menu',     'Menu',     'system', 1);
INSERT INTO llx_c_type_container (entity, code, label, module, active) VALUES (__ENTITY__, 'other',    'Other',    'system', 1);
