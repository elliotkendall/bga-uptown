<?php
 /**
  *------
  * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
  * Uptown implementation : © Elliot Kendall <elliotkendall@gmail.com>
  * 
  * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
  * See http://en.boardgamearena.com/#!doc/Studio for more information.
  * -----
  * 
  * uptown.game.php
  *
  * This is the main file for your game logic.
  *
  * In this PHP file, you are going to defines the rules of the game.
  *
  */
require_once( APP_GAMEMODULE_PATH.'module/table/table.game.php' );

class Uptown extends Table {
  function __construct() {
    parent::__construct();
    // This is evidently necessary even if we're not using any globals
    self::initGameStateLabels(array());        

    $this->tiles = self::getNew("module.common.deck");
    $this->tiles->init("tile");
  }
	
  protected function getGameName() {
    // Used for translations and stuff. Please do not modify.
    return "uptown";
  }	

  /*
  setupNewGame:

  This method is called only once, when a new game is launched. In this
  method, you must setup the game according to the game rules, so that the
  game is ready to be played.
  */
  protected function setupNewGame($players, $options = array()) {
    // Set the colors of the players with HTML color code
    // The default below is red/green/blue/orange/brown
    // The number of colors defined here must correspond to the maximum number of players allowed for the gams
    $gameinfos = self::getGameinfos();
    $default_colors = $gameinfos['player_colors'];
 
    // Create players
    // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
    $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
    $values = array();
    foreach($players as $player_id => $player) {
      $color = array_shift($default_colors);
      $values[] = "('".$player_id."','$color','".$player['player_canal']."','".addslashes($player['player_name'])."','".addslashes($player['player_avatar'])."')";
    }
    $sql .= implode($values, ',');
    self::DbQuery($sql);
    self::reattributeColorsBasedOnPreferences($players, $gameinfos['player_colors']);
    self::reloadPlayersBasicInfos();
        
    /************ Start the game initialization *****/
    // Init game statistics
    // (note: statistics used in this file must be defined in your stats.inc.php file)
    //self::initStat( 'table', 'table_teststat1', 0 );    // Init a table statistics
    //self::initStat( 'player', 'player_teststat1', 0 );  // Init a player statistics (for all players)

    foreach($players as $player_id => $player) {
      $tiles = array();
      foreach ($this->tile_values as $type => $name) {
        $tiles[] = array(
         'type' => $player_id,
         'type_arg' => $type,
         'nbr' => 1);
      }
      // Create a deck for this player
      $this->tiles->createCards($tiles, 'deck_' . $player_id);
      $this->tiles->shuffle('deck_' . $player_id);

      // Draw a hand of tiles
      $this->tiles->pickCards(5, 'deck_' . $player_id, $player_id);
    }

    // Activate first player (which is in general a good idea :) )
    $this->activeNextPlayer();

    /************ End of the game initialization *****/
  }

  /*
  getAllDatas: 
        
  Gather all informations about current game situation (visible by the
  current player).
        
  The method is called each time the game interface is displayed to a
  player, ie:
    _ when the game starts
    _ when a player refreshes the game page (F5)
  */
  protected function getAllDatas() {
    $result = array();

    // !! We must only return informations visible by this player !!
    $current_player_id = self::getCurrentPlayerId();
    $result['my_player_id'] = $current_player_id;
    
    // Get information about players
    // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
    $sql = "SELECT player_id id, player_score score FROM player ";

    $result['players'] = array();
    foreach(self::getCollectionFromDb($sql) as $player_id => $info) {
      $result['players'][$player_id] = $info;
      $result['players'][$player_id]['captured'] =
       $this->tiles->getCardsInLocation('captured', $player_id);
      $result['players'][$player_id]['handcount'] =
       count($this->tiles->getCardsInLocation('hand', $current_player_id));
      $result['players'][$player_id]['deckcount'] =
       count($this->tiles->getCardsInLocation('deck_' . $current_player_id));
    }

    $result['hand'] = $this->tiles->getCardsInLocation('hand', $current_player_id);

    $result['board'] = $this->tiles->getCardsInLocation('board');

    $result['groups'] = $this->findGroups();

    return $result;
  }

    /*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with the "updateGameProgression" property set to true 
        (see states.inc.php)
    */
    function getGameProgression() {
      $players = self::loadPlayersBasicInfos();
      $tilesInPlay = 0;
      foreach (array_keys($players) as $player_id) {
        $tilesInPlay += $this->tiles->countCardInLocation('hand', $player_id);
        $tilesInPlay += $this->tiles->countCardInLocation('deck_' . $player_id);
      }
      $numPlayers = count($players);
      return round((1 - (($tilesInPlay - 4 * $numPlayers) / (24 * $numPlayers))) * 100);
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////    

  /*
  In this space, you can put any utility methods useful for your game logic
  */

  // Per "Main game logic" doc, this should have been in the API
  function dbSetScore($player_id, $count) {
    $this->DbQuery("UPDATE player SET player_score='$count' WHERE player_id='$player_id'");
  }

  // Similar to the provided dbIncScore in the docs, but (1) they don't
  // provide a version for aux score, and (2) their implementation is really
  // inefficient
  function dbIncAuxScore($player_id, $inc) {
    $this->DbQuery("UPDATE player SET player_score_aux=player_score_aux+$inc WHERE player_id='$player_id'");
  }

  function findGroups() {
    $tiles = $this->tiles->getCardsInLocation('board');

    // Create an empty board array
    $board = array();
    for($square=0;$square<82;$square++) {
      $board[$square] = NULL;
    }

    // Populate it with data from the tiles in the board location
    foreach ($tiles as $tile) {
      $board[$tile['location_arg']] = $tile['type'];
    }

    // Look through the board in order
    $groups = array();
    for($square=0;$square<82;$square++) {
      if ($board[$square] == NULL) {
        // Empty square
        continue;
      }
      $player = $board[$square];
      if (! isset($groups[$player])) {
        $groups[$player] = array();
      }

      // Is the square above and/or to the left part of an existing group?
      // Because of the order we're looping through the board we only
      // need to worry about those two directions
      $adjgroups = array();
      foreach(array_keys($groups[$player]) as $gid) {
        // square % 9 != 0 means we don't wrap from the first column
        // of one row back to the last column of the previous one
        if (($square % 9 != 0 && in_array($square-1, $groups[$player][$gid]))
         || in_array($square-9, $groups[$player][$gid])) {
          $adjgroups[] = $gid;
        }
      }
      if (count($adjgroups) == 0) {
        // No adjacent groups, so start a new one
        $groups[$player][] = array($square);
      } else if (count($adjgroups) == 1) {
        // Join that group
        $groups[$player][$adjgroups[0]][] = $square;
      } else {
        // Combine groups and join the result
        $groups[$player][$adjgroups[0]] = array_merge(
         $groups[$player][$adjgroups[0]],
         $groups[$player][$adjgroups[1]],
         array($square));
        // Remove the combined group. We can't use unset() here or the
        // keys would potentially not be contiguous, leading to weird
        // issues with Javascript arrays vs. objects
        array_splice($groups[$player], $adjgroups[1], 1);
      }
    }
    return $groups;
  }


//////////////////////////////////////////////////////////////////////////////
//////////// Player actions
//////////// 

    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in uptown.action.php)
    */

  function playTile($deckid, $location) {
    // Check that this is the player's turn and that it is a "possible action" at this game state (see states.inc.php)
    self::checkAction('playTile');

    $player_id = self::getActivePlayerId();
    $players = self::loadPlayersBasicInfos();

    $existing = $this->tiles->getCardsInLocation('board', $location);

    $captured = FALSE;
    $captured_tile = NULL;
    $capture_target = NULL;
    foreach ($existing as $id => $tile) {
      $this->tiles->moveCard($id, 'captured', $player_id);
      $capture_target = $tile['type'];
      $captured_tile = $tile['type_arg'];
      $captured = TRUE;
      $this->dbIncAuxScore($player_id, -1);
      // There should only ever be one tile in a location
      break;
    }
        
    // Put the tile on the board
    $this->tiles->moveCard($deckid, 'board', $location);

    // Recalculate tile groups and set scores accordingly
    $groups = $this->findGroups();
    foreach ($groups as $gpid => $pgroups) {
      $this->dbSetScore($gpid, -1 * count($pgroups));
    }
            
    // Notify all players about the tile played
    $player_name = self::getActivePlayerName();
    $type = $this->tiles->getCard($deckid)['type_arg'];
    $tile_name = $this->tile_values[$type];

    $message = "${player_name} plays ${tile_name}";
    $ret = array(
     'i18n' => array ('tile_name'),
     'player_id' => $player_id,
     'location' => $location,
     'tile_type' => $type,
     'groups' => $groups
    );
    if ($captured) {
      $capture_target_name = $players[$capture_target]['player_name'];
      $captured_tile_name = $this->tile_values[$captured_tile];
      $message .= ", capturing ${capture_target_name}'s ${captured_tile_name}";
      $ret['i18n'][] = 'captured_tile_name';
    }

    self::notifyAllPlayers('playTile', clienttranslate($message), $ret);

    // Draw a new tile to replace it
    $newTile = $this->tiles->pickCard('deck_' . $player_id, $player_id);
    if ($newTile !== NULL) {
      // Notify the player who drew it
      $type = $newTile['type_arg'];
      self::notifyPlayer($player_id, 'drawTile', '',
       array (
        'tile' => $type,
        'id' => $newTile['id']));
      // Notify everyone else
      foreach (array_keys($players) as $thispid) {
        if ($thispid == $player_id) {
          continue;
        }
        self::notifyPlayer($thispid, 'drawTileOther', '',
         array (
          'who' => $player_id));
      }
    }
    
    $this->gamestate->nextState('playTile');
  }

    
//////////////////////////////////////////////////////////////////////////////
//////////// Game state arguments
////////////

    /*
        Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
        These methods function is to return some additional information that is specific to the current
        game state.
    */

    /*
    
    Example for game state "MyGameState":
    
    function argMyGameState()
    {
        // Get some values from the current game situation in database...
    
        // return values:
        return array(
            'variable1' => $value1,
            'variable2' => $value2,
            ...
        );
    }    
    */

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

  /*
  Here, you can create methods defined as "game state actions" (see "action"
  property in states.inc.php).  The action method of state X is called
  everytime the current game state is set to X.
  */

  function stNextPlayer() {
    // Check for end game
    $players = self::loadPlayersBasicInfos();
    $gameover = TRUE;
    foreach (array_keys($players) as $player_id) {
      if ($this->tiles->countCardInLocation('hand', $player_id) > 4) {
        $gameover = FALSE;
        break;
      }
    }
    if ($gameover) {
      $this->gamestate->nextState('gameEnd');
    } else {
      $player_id = self::activeNextPlayer();
      self::giveExtraTime($player_id);
      $this->gamestate->nextState('nextPlayer');
    }
  }

//////////////////////////////////////////////////////////////////////////////
//////////// Zombie
////////////

    /*
        zombieTurn:
        
        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player ends appropriately
        (ex: pass).
        
        Important: your zombie code will be called when the player leaves the game. This action is triggered
        from the main site and propagated to the gameserver from a server, not from a browser.
        As a consequence, there is no current player associated to this action. In your zombieTurn function,
        you must _never_ use getCurrentPlayerId() or getCurrentPlayerName(), otherwise it will fail with a "Not logged" error message. 
    */

    function zombieTurn( $state, $active_player )
    {
    	$statename = $state['name'];
    	
        if ($state['type'] === "activeplayer") {
            switch ($statename) {
                default:
                    $this->gamestate->nextState( "zombiePass" );
                	break;
            }

            return;
        }

        if ($state['type'] === "multipleactiveplayer") {
            // Make sure player is in a non blocking status for role turn
            $this->gamestate->setPlayerNonMultiactive( $active_player, '' );
            
            return;
        }

        throw new feException( "Zombie mode not supported at this game state: ".$statename );
    }
    
///////////////////////////////////////////////////////////////////////////////////:
////////// DB upgrade
//////////

    /*
        upgradeTableDb:
        
        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.
    
    */
    
    function upgradeTableDb( $from_version )
    {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345
        
        // Example:
//        if( $from_version <= 1404301345 )
//        {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "ALTER TABLE DBPREFIX_xxxxxxx ....";
//            self::applyDbUpgradeToAllDB( $sql );
//        }
//        if( $from_version <= 1405061421 )
//        {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
//            self::applyDbUpgradeToAllDB( $sql );
//        }
//        // Please add your future database scheme changes here
//
//


    }    
}
