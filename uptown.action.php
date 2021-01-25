<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Uptown implementation : © Elliot Kendall <elliotkendall@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on https://boardgamearena.com.
 * See http://en.doc.boardgamearena.com/Studio for more information.
 * -----
 * 
 * uptown.action.php
 *
 * Uptown main action entry point
 *
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *       
 * If you define a method "myAction" here, then you can call it from your javascript code with:
 * this.ajaxcall( "/uptown/uptown/myAction.html", ...)
 *
 */
  
class action_uptown extends APP_GameAction { 
  // Constructor: please do not modify
  public function __default() {
    if (self::isArg( 'notifwindow')) {
      $this->view = "common_notifwindow";
      $this->viewArgs['table'] = self::getArg("table", AT_posint, true);
    } else {
      $this->view = "uptown_uptown";
      self::trace( "Complete reinitialization of board game" );
    }
  } 

  public function playTile() {
    self::setAjaxMode();
    $tile_id = self::getArg("tile", AT_posint, true);
    $location = self::getArg("location", AT_posint, true);
    $this->game->playTile($tile_id, $location);
    self::ajaxResponse();
  }
}
