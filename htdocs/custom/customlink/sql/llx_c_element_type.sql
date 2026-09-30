-- ===================================================================
-- Copyright (C) 2014-2020 Charlene Benke <charlie@patas-monkey.com>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 2 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program. If not, see <http://www.gnu.org/licenses/>.
--
-- ===================================================================

create table llx_c_element_type
(
  rowid				  integer AUTO_INCREMENT PRIMARY KEY,
  type				  varchar (32),	-- type de l'élément tel qu'utilisé dans la table element_element
  label				  text,			    -- nom de l'élément 
  classpath			text,         -- chemin de la classe de l'élément
  subelement		text,         -- répertoire de l'élément
  module			  text,         -- nom du module
  translatefile	text,         -- fichier de lang
  classfile			text,         
  className			text,
  incore			  integer		    -- le module est présent dans le core
)ENGINE=innodb;
