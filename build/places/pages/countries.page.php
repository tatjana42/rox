<?php
/*
Copyright (c) 2007-2009 BeVolunteer

This file is part of BW Rox.

BW Rox is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

BW Rox is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, see <http://www.gnu.org/licenses/> or
write to the Free Software Foundation, Inc., 59 Temple Place - Suite 330,
Boston, MA  02111-1307, USA.
*/


/**
 * @author shevek
 */

/**
 * Countries pages
 *
 * @package Apps
 * @subpackage Places
 */
class CountriesPage extends PageWithActiveSkin
{
    protected function getPageTitle() {
        $words = $this->getWords();
        return $words->getBuffered('Countries') . ' - BeWelcome';
    }

    protected function teaserHeadline()
    {
        $layoutkit = $this->layoutkit;
        $formkit = $layoutkit->formkit;
        $words = $layoutkit->getWords();
        return $words->get('Countries');
    }

    protected function getStylesheets() {
       $stylesheets = parent::getStylesheets();
       $stylesheets[] = 'styles/css/minimal/screen/custom/places.css?2';
       $stylesheets[] = 'styles/css/minimal/screen/basemod_minimal_col3.css';
       return $stylesheets;
    }
}