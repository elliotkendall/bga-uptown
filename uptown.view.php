<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Uptown implementation : © Elliot Kendall <elliotkendall@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 */
  
  require_once( APP_BASE_PATH."view/common/game.view.php" );
  
  class view_uptown_uptown extends game_view
  {
    function getGameName() {
        return "uptown";
    }    
    static function getSymbol($y, $x) {
      if ($x < 3) {
        if ($y < 3) {
          return 'guy';
        } else if ($y < 6) {
          return 'ring';
        } else {
          return 'lady';
        }
      } else if ($x < 6) {
        if ($y < 3) {
          return 'lamp';
        } else if ($y < 6) {
          return 'city';
        } else {
          return 'sax';
        }
      } else {
        if ($y < 3) {
          return 'car';
        } else if ($y < 6) {
          return 'cards';
        } else {
          return 'wine';
        }
      }
    }
  	function build_page( $viewArgs )
  	{		
        global $g_user;
        $players = $this->game->loadPlayersBasicInfos();
        $template = self::getGameName() . "_" . self::getGameName();

        // this will inflate our player block with actual players data
        $this->page->begin_block($template, "player_area");
        foreach ($players as $player_id => $info) {
          if ($player_id == $g_user->get_id()) {
            continue;
          }
          $this->page->insert_block("player_area", array(
            "PLAYER_ID" => $player_id,
            "PLAYER_NAME" => $players[$player_id]['player_name'],
            "PLAYER_COLOR" => $players[$player_id]['player_color']));
        }

        // fill the board with squares
        $this->page->begin_block($template, "square");
        $hor_scale = 52.5;
        $ver_scale = 52.5;
        for($x=0; $x<9; $x++) {
          for($y=0; $y<9; $y++) {
            $this->page->insert_block( "square", array(
             'ID' => $x + (9 * $y),
             'NUMBER' => $x+1,
             'LETTER' => chr($y+65),
             'SYMBOL' => self::getSymbol($x, $y),
             'LEFT' => round( ($x)*$hor_scale+100 ),
             'TOP' => round( ($y)*$ver_scale+100 )
            ));
          }        
        }

        // this will make our My Hand text translatable
        $this->tpl['MY_TILES'] = self::_("My tiles");

        /*********** Do not change anything below this line  ************/
  	}
  }
  

