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

// Local constants
define("SCORING_UPTOWN", 1);
define("SCORING_BLOCKERS", 2);

class Uptown extends Table {
  function __construct() {
    parent::__construct();
    self::initGameStateLabels(array("scoring_rules" => 100));

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

    foreach(array('final_groups_number', 'maximum_groups_number', 'tiles_captured') as $stat) {
      self::initStat('table', $stat, 0);
    }
    foreach(array('final_groups_number', 'maximum_groups_number', 'opponents_tiles_captured_count', 'tiles_captured_by_opponents_count') as $stat) {
      self::initStat('player', $stat, 0);
    }

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

      if (count($players) == 2) {
        // Create a new tiles array with _alt player_id
        $tiles = array();
        foreach ($this->tile_values as $type => $name) {
          $tiles[] = array(
           'type' => $player_id . '_alt',
           'type_arg' => $type,
           'nbr' => 1);
        }

        // Create the second deck
        $this->tiles->createCards($tiles, 'dckalt_' . $player_id);
        $this->tiles->shuffle('dckalt_' . $player_id);

        // Draw a hand of tiles
        $this->tiles->pickCardsForLocation(5, 'dckalt_' . $player_id,
         'hand_alt', $player_id);
      }
      
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
    $players = self::getCollectionFromDb($sql);
    foreach($players as $player_id => $info) {
      $result['players'][$player_id] = $info;
      $result['players'][$player_id]['captured'] =
       $this->tiles->getCardsInLocation('captured', $player_id);
      $result['players'][$player_id]['handcount'] =
       count($this->tiles->getCardsInLocation('hand', $player_id));
      $result['players'][$player_id]['deckcount'] =
       count($this->tiles->getCardsInLocation('deck_' . $player_id));
      if (count($players) == 2) {
        $result['players'][$player_id]['handcount_alt'] =
         count($this->tiles->getCardsInLocation('hand_alt', $player_id));
        $result['players'][$player_id]['deckcount_alt'] =
         count($this->tiles->getCardsInLocation('dckalt_' . $player_id));
      }
    }

    $result['hand'] = $this->tiles->getCardsInLocation('hand', $current_player_id);
    if (count($players) == 2) {
      $result['hand_alt'] = $this->tiles->getCardsInLocation('hand_alt', $current_player_id);
    }

    $result['board'] = $this->tiles->getCardsInLocation('board');

    $result['groups'] = $this->findGroups();

    $result['protected'] = $this->findProtectedTiles($result['groups']);

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
      if ($numPlayers == 2) {
        return round((1 - (($tilesInPlay - 4 * $numPlayers) / (24 * $numPlayers))) * 100);
      } else {
        return round((1 - (($tilesInPlay - 4 * $numPlayers) / (48 * $numPlayers))) * 100);
      }
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////    

  /*
  In this space, you can put any utility methods useful for your game logic
  */

  function isPlayerZombie($player_id) {
    $sql = "SELECT player_zombie FROM player where player_id=" . $player_id;
    $result = self::getNonEmptyObjectFromDB($sql);
    return $result['player_zombie'];
  }

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

  // Find all the groups present in the current board data
  function findGroups() {
    $tiles = $this->tiles->getCardsInLocation('board');

    // Populate board array with data from the tiles in the board location
    $board = array();
    foreach ($tiles as $tile) {
      $board[$tile['location_arg']] = $tile['type'];
    }
    return $this->findGroupsReal($board);
  }

  // Find all groups present in a list of locations with no owner
  // information
  function findGroupsNoPlayer($board) {
    $board = array_combine($board, array_fill(0, count($board), 'foo'));
    $ret = $this->findGroupsReal($board);
    if (isset($ret['foo'])) {
      return $ret['foo'];
    } else {
      return array();
    }
  }


  // Do the actual work of finding groups. Expects an array of format
  // [ {location: playerid}, {location: playerid}, ... ]
  function findGroupsReal($board) {
    // Look through the board in order
    $groups = array();
    ksort($board);
    foreach($board as $square => $player) {
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

  // Does a particular list of tile locations comprise a group, i.e.
  // is each location adjacent to at least one other?
  function isGroup($tiles) {
    $groups = $this->findGroupsNoPlayer($tiles);
    return (count($groups) == 1 && count($groups[0]) == count($tiles));
  }

  // Find the 'protected' tiles from the output of findGroups*(), i.e.
  // tiles that are illegal to capture
  function findProtectedTiles($groups) {
    $protected = array();
    foreach($groups as $player => $pgroups) {
      foreach($pgroups as $group) {
        for($i=0;$i<count($group);$i++) {
          if (count($group) == 1) {
            continue;
          }
          # Make a copy so we're not modifying the original
          $g = $group;
          unset($g[$i]);
          if (! $this->isGroup($g)) {
            $protected[] = $group[$i];
          }
        }
      }
    }
    return $protected;
  }

  function locationToTypeIDs($location) {
    $lookup = array(
      0 => array('guy', '1', 'A', '$'),
      1 => array('guy', '2', 'A', '$'),
      2 => array('guy', '3', 'A', '$'),
      3 => array('ring', '4', 'A', '$'),
      4 => array('ring', '5', 'A', '$'),
      5 => array('ring', '6', 'A', '$'),
      6 => array('lady', '7', 'A', '$'),
      7 => array('lady', '8', 'A', '$'),
      8 => array('lady', '9', 'A', '$'),
      9 => array('guy', '1', 'B', '$'),
      10 => array('guy', '2', 'B', '$'),
      11 => array('guy', '3', 'B', '$'),
      12 => array('ring', '4', 'B', '$'),
      13 => array('ring', '5', 'B', '$'),
      14 => array('ring', '6', 'B', '$'),
      15 => array('lady', '7', 'B', '$'),
      16 => array('lady', '8', 'B', '$'),
      17 => array('lady', '9', 'B', '$'),
      18 => array('guy', '1', 'C', '$'),
      19 => array('guy', '2', 'C', '$'),
      20 => array('guy', '3', 'C', '$'),
      21 => array('ring', '4', 'C', '$'),
      22 => array('ring', '5', 'C', '$'),
      23 => array('ring', '6', 'C', '$'),
      24 => array('lady', '7', 'C', '$'),
      25 => array('lady', '8', 'C', '$'),
      26 => array('lady', '9', 'C', '$'),
      27 => array('lamp', '1', 'D', '$'),
      28 => array('lamp', '2', 'D', '$'),
      29 => array('lamp', '3', 'D', '$'),
      30 => array('city', '4', 'D', '$'),
      31 => array('city', '5', 'D', '$'),
      32 => array('city', '6', 'D', '$'),
      33 => array('sax', '7', 'D', '$'),
      34 => array('sax', '8', 'D', '$'),
      35 => array('sax', '9', 'D', '$'),
      36 => array('lamp', '1', 'E', '$'),
      37 => array('lamp', '2', 'E', '$'),
      38 => array('lamp', '3', 'E', '$'),
      39 => array('city', '4', 'E', '$'),
      40 => array('city', '5', 'E', '$'),
      41 => array('city', '6', 'E', '$'),
      42 => array('sax', '7', 'E', '$'),
      43 => array('sax', '8', 'E', '$'),
      44 => array('sax', '9', 'E', '$'),
      45 => array('lamp', '1', 'F', '$'),
      46 => array('lamp', '2', 'F', '$'),
      47 => array('lamp', '3', 'F', '$'),
      48 => array('city', '4', 'F', '$'),
      49 => array('city', '5', 'F', '$'),
      50 => array('city', '6', 'F', '$'),
      51 => array('sax', '7', 'F', '$'),
      52 => array('sax', '8', 'F', '$'),
      53 => array('sax', '9', 'F', '$'),
      54 => array('car', '1', 'G', '$'),
      55 => array('car', '2', 'G', '$'),
      56 => array('car', '3', 'G', '$'),
      57 => array('cards', '4', 'G', '$'),
      58 => array('cards', '5', 'G', '$'),
      59 => array('cards', '6', 'G', '$'),
      60 => array('wine', '7', 'G', '$'),
      61 => array('wine', '8', 'G', '$'),
      62 => array('wine', '9', 'G', '$'),
      63 => array('car', '1', 'H', '$'),
      64 => array('car', '2', 'H', '$'),
      65 => array('car', '3', 'H', '$'),
      66 => array('cards', '4', 'H', '$'),
      67 => array('cards', '5', 'H', '$'),
      68 => array('cards', '6', 'H', '$'),
      69 => array('wine', '7', 'H', '$'),
      70 => array('wine', '8', 'H', '$'),
      71 => array('wine', '9', 'H', '$'),
      72 => array('car', '1', 'I', '$'),
      73 => array('car', '2', 'I', '$'),
      74 => array('car', '3', 'I', '$'),
      75 => array('cards', '4', 'I', '$'),
      76 => array('cards', '5', 'I', '$'),
      77 => array('cards', '6', 'I', '$'),
      78 => array('wine', '7', 'I', '$'),
      79 => array('wine', '8', 'I', '$'),
      80 => array('wine', '9', 'I', '$')
    );
    return $lookup[$location];
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

    // Sanity checks
    $location = intval($location);
    if ($location < 0 || $location > 80) {
      throw new feException('Invalid location');
    }

    $deckid = intval($deckid);
    $tile = $this->tiles->getCard($deckid);

    // These are all "unexpected" errors, so we won't bother translating
    // them
    if (($tile['location'] !== 'hand' && $tile['location'] !== 'hand_alt')
     || $tile['location_arg'] !== $player_id) {
      throw new feException("You don't have that tile");
      return;
    }

    if (($tile['location'] == 'hand'
     && count($this->tiles->getCardsInLocation('hand', $player_id)) < 5)
     || ($tile['location'] == 'hand_alt'
     && count($this->tiles->getCardsInLocation('hand_alt', $player_id)) < 5)) {
      throw new feException("You have already played all but four tiles of that color");
      return;
    }

    if (! in_array($this->tile_values[$tile['type_arg']],
     $this->locationToTypeIDs($location))) {
      throw new feException("That tile doesn't go there");
    }
    
    $target = $this->tiles->getCardsInLocation('board', $location);
    if (count($target) == 1) {
      $target_type = array_shift($target)['type'];
      if ($target_type == $player_id || $target_type == $player_id . '_alt') {
        throw new feException("You can't capture your own tile");
      }
    }

    if (in_array($location, $this->findProtectedTiles($this->findGroups()))) {
      throw new feException("Capturing that tile would break up a group");
    }

    // Either hand or hand_alt
    $tile_location = $tile['location'];

    $captured = FALSE;
    $captured_tile = NULL;
    $capture_target = NULL;

    // We have to do this before we count captured pieces
    foreach ($existing as $id => $tile) {
      $this->tiles->moveCard($id, 'captured', $player_id);

      // There should only ever be one existing tile in a location
      break;
    }

    // For each player, find the opponent they've captured the most and
    // fewest tiles from. We need this for Blockers! scoring or to update
    // stats if we captured a tile
    if (self::getGameStateValue('scoring_rules') == SCORING_BLOCKERS
     || count($existing) > 0) {
      $statsByPlayer = array();
      foreach (array_keys($players) as $thispid) {
        $captureCounts = array();
        foreach (array_keys($players) as $oppid) {
          $captureCounts[$oppid] = 0;
        }
        foreach ($this->tiles->getCardsInLocation('captured', $thispid)
         as $id => $card) {
          $player = $card['type'];
          if (substr($player, -4) == '_alt') {
            // Count players and their alts together
            $player = substr($player, 0, -4);
          }
          $captureCounts[$player]++;
        }
        $statsByPlayer[$thispid]['max'] = 0;
        $statsByPlayer[$thispid]['min'] = 99;
        foreach ($captureCounts as $player => $count) {
          if ($count > $statsByPlayer[$thispid]['max']) {
            $statsByPlayer[$thispid]['max'] = $count;
          }
          if ($count < $statsByPlayer[$thispid]['min']) {
            $statsByPlayer[$thispid]['min'] = $count;
          }
        }
      }
    }

    // This loop will run 0 or 1 times depending on whether or not there was
    // already a tile at the location we played
    foreach ($existing as $id => $tile) {
      if (substr($tile['type'], -4) == '_alt') {
        $capture_target = substr($tile['type'], 0, -4);
        $capture_hand = 'hand_alt';
      } else {
        $capture_target = $tile['type'];
        $capture_hand = 'hand';
      }
      $captured_tile = $tile['type_arg'];
      $captured = TRUE;
      if (substr($player_id, -4) == '_alt') {
        $statplayerid = substr($player_id, 0, -4);
      } else {
        $statplayerid = $player_id;
      }
      $this->dbIncAuxScore($statplayerid, -1);
      self::incStat(1, 'tiles_captured');
      self::incStat(1, 'opponents_tiles_captured_count', $statplayerid);
      self::incStat(1, 'tiles_captured_by_opponents_count', $capture_target);
      self::setStat($statsByPlayer[$statplayerid]['max'], 'max_captured_from_one_opponent', $player_id);
      self::setStat($statsByPlayer[$statplayerid]['min'], 'min_captured_from_one_opponent', $player_id);

    }
        
    // Put the tile on the board
    $this->tiles->moveCard($deckid, 'board', $location);

    // Recalculate tile groups and set scores and stats accordingly
    $groups = $this->findGroups();
    $total = 0;
    $newscores = array();
    // We can't just loop over $groups directly since we want to update all
    // player scores, even people with no groups
    $toLoop = array_keys($players);
    if (count($players) == 2) {
      foreach(array_keys($players) as $base) {
        $toLoop[] = $base . '_alt';
      }
    }
    foreach ($toLoop as $gpid) {
      if (isset($groups[$gpid])) {
        $pgroups = $groups[$gpid];
      } else {
        $pgroups = array();
      }
      $count = count($pgroups);
      if (self::getGameStateValue('scoring_rules') == SCORING_BLOCKERS) {
        $newscore = $count + $statsByPlayer[$gpid]['max'];
      } else {
        // Default to Uptown scoring for games without this value set, e.g. 
        // from before we added the option
        $newscore = $count;
      }

      $newscores[$gpid] = $newscore;
      $total += $count;
    }

    $reportscores = array();
    foreach(array_keys($players) as $gpid) {
      // We don't need to do anything for the alt scores themselves
      if (substr($gpid, -4) == '_alt') {
        continue;
      }
      // If this player has an alt, combine the scores
      if (isset($newscores[$gpid . '_alt'])) {
        $newscores[$gpid] += $newscores[$gpid . '_alt'];
      }

      self::setStat($newscores[$gpid], 'final_groups_number', $gpid);
      if ($newscores[$gpid] > self::getStat('maximum_groups_number', $gpid)) {
        self::setStat($newscores[$gpid], 'maximum_groups_number', $gpid);
      }

      $reportscores[$gpid] = -1 * $newscores[$gpid];
      $this->dbSetScore($gpid, $reportscores[$gpid]);
    }

    self::setStat($total, 'final_groups_number');
    if ($total > self::getStat('maximum_groups_number')) {
        self::setStat($total, 'maximum_groups_number');
    }
    // Notify all players about the tile played
    $type = $this->tiles->getCard($deckid)['type_arg'];

    // Note that the variables in this string are NOT interpreted
    // by PHP! Despite the syntax, this is a single-quoted string
    // so PHP will ignore the variables. They are later interpreted
    // on the client side
    $message = clienttranslate('${player_name} plays ${tile_name}');

    // We haven't yet drawn a tile to replace the one that was played, so
    // this will be off by one
    $deckcount = count($this->tiles->getCardsInLocation('deck_' . $player_id));
    $deckcount_alt = count($this->tiles->getCardsInLocation('dckalt_' . $player_id));
    if ($tile_location == 'hand' && $deckcount > 0) {
      $deckcount--;
    } else if ($tile_location == 'hand_alt' && $deckcount_alt > 0) {
      $deckcount_alt--;
    }

    $ret = array(
     'i18n' => array ('tile_name'),
     'tile_name' => $this->tile_values[$type],
     'player_name' => self::getActivePlayerName(),
     'player_id' => $player_id,
     'which_hand' => $tile_location,
     'location' => $location,
     'tile_type' => $type,
     'deckcount' => $deckcount,
     'deckcount_alt' => $deckcount_alt,
     'groups' => $groups,
     'protected' => $this->findProtectedTiles($groups),
     'scores' => $reportscores,
     'capture_target_id' => NULL,
     'preserve' => array(2 => 'capture_target_id', 'which_hand', 'capture_which_hand')
    );
    if ($captured) {
      // See the above comment about $message
      $message = clienttranslate('${player_name} plays ${tile_name}, capturing ${capture_target_name}\'s ${captured_tile_name}');
      $ret['capture_target_name'] = $players[$capture_target]['player_name'];
      $ret['capture_target_id'] = $capture_target;
      $ret['captured_tile_name'] = $this->tile_values[$captured_tile];
      $ret['capture_which_hand'] = $capture_hand;
      $ret['i18n'][] = 'captured_tile_name';
    }

    self::notifyAllPlayers('playTile', $message, $ret);

    // Draw a new tile to replace it
    if ($tile_location == 'hand') {
      $newTile = $this->tiles->pickCard('deck_' . $player_id, $player_id);
    } else {
      $newTile = $this->tiles->pickCardForLocation('dckalt_' . $player_id, 'hand_alt', $player_id);
    }
    if ($newTile !== NULL) {
      // Notify the player who drew it
      $type = $newTile['type_arg'];
      self::notifyPlayer($player_id, 'drawTile', '',
       array (
        'tile' => $type,
        'which_hand' => $tile_location,
        'id' => $newTile['id']));

      // Notify everyone else.  This will also notify the player who drew
      // it, but that's unavoidable if we want to notify spectators
      self::notifyAllPlayers('drawTileOther', '',
         array (
          'which_hand' => $tile_location,
          'who' => $player_id));
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
      if ($this->isPlayerZombie($player_id)) {
        continue;
      }

      $altcount = $this->tiles->countCardInLocation('hand_alt', $player_id);
      if ($altcount > 0) {
        // Two player game
        if ($altcount + $this->tiles->countCardInLocation('hand', $player_id) > 8) {
          $gameover = FALSE;
          break;
        }
      } else {
        // 3+ player game
        if ($this->tiles->countCardInLocation('hand', $player_id) > 4) {
          $gameover = FALSE;
          break;
        }
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

    function zombieTurn( $state, $active_player ) {
      if ($state['name'] == 'playerTurn') {
        $this->gamestate->nextState( "zombiePass" );
      } else {
        throw new feException( "Zombie mode not supported at this game state:".$state['name'] );
      }
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
