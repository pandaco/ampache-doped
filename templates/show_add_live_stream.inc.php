<?php
/*

 Copyright (c) 2001 - 2007 Ampache.org
 All rights reserved.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 as published by the Free Software Foundation; version 2
 of the License.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.

*/

?>
<?php show_box_top(_('Add Radio Station')); ?>
<form name="radio" method="post" action="<?php echo Config::get('web_path'); ?>/radio.php?action=create">
<table>
<tr>
	<td><?php echo _('Name'); ?></td>
	<td>
		<input type="text" name="name" size="20" value="<?php echo scrub_out($_REQUEST['name']); ?>" />
		<?php Error::display('name'); ?>
	</td>
</tr>
<tr>
	<td><?php echo _('Station Homepage'); ?></td>
	<td>
		<input type="text" name="site_url" value="<?php echo scrub_out($_REQUEST['site_url']); ?>" />
		<?php Error::display('site_url'); ?>
	</td>
</tr>
<tr>
	<td><?php echo _('Station URL'); ?></td>
	<td>
		<input type="text" name="url" value="<?php echo scrub_out($_REQUEST['url']); ?>" />
		<?php Error::display('url'); ?>
	</td>
</tr>
<tr>
	<td><?php echo _('Station Frequency'); ?></td>
	<td>
		<input type="text" name="frequency" value="<?php echo scrub_out($_REQUEST['frequency']); ?>" />
	</td>
</tr>
<tr>
	<td><?php echo _('Station call-sign'); ?></td>
	<td>
		<input type="text" name="call_sign" value="<?Php echo scrub_out($_REQUEST['call_sign']); ?>" />
	</td>
</tr>
<tr>
	<td><?php echo _('Genre'); ?></td>
	<td>
		<?php echo show_genre_select('genre',intval($_REQUEST['genre'])); ?>
	</td>
</tr>
<tr>
	<td><?php echo _('Catalog'); ?></td>
	<td>
		<?php echo show_catalog_select('catalog',intval($_REQUEST['catalog'])); ?>
	</td>
</tr>
<tr>
	<td colspan="2">
	<input class="button" type="submit" value="<?php echo _('Add'); ?>" />
	</td>
</tr>
</table>
</form>
<?php show_box_bottom(); ?>