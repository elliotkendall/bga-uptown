/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Uptown implementation : © Elliot Kendall <elliotkendall@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

define([
  "dojo","dojo/_base/declare",
  "ebg/core/gamegui",
  "ebg/counter",
  "ebg/stock"
],
function (dojo, declare) {
  return declare("bgagame.uptown", ebg.core.gamegui, {
    constructor: function() {
      this.playerHand = null;
      this.tilewidth = 50;
      this.tileheight = 50;

      this.colors = ['blue', 'green', 'orange', 'red', 'yellow'];
      this.colorsByHex = {
        "c5f0f9": "blue",
        "c8e6c8": "green",
        "f28c60": "orange",
        "f03f84": "red",
        "ffff8b": "yellow"
      };
      this.tiles = [
       '1', '2', '3', '4', '5', '6', '7', '8', '9',
       'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I',
       'guy', 'ring', 'lady', 'lamp', 'city', 'sax', 'car', 'cards', 'wine',
       '$'
      ];

    },
    /*
      setup:
            
      This method must set up the game user interface according to current
      game situation specified in parameters.
            
      The method is called each time the game interface is displayed to a
      player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
            
      "gamedatas" argument contains all datas retrieved by your
      "getAllDatas" PHP method.
    */
        
    setup: function(gamedatas) {
      console.log(gamedatas);

      this.myColor = this.colorsByHex[gamedatas.players[gamedatas.my_player_id].color];
      this.myId = gamedatas.my_player_id;

      // Player hand
      this.playerHand = new ebg.stock();
      this.playerHand.create(this, $('player_hand_self'),
       this.tilewidth, this.tileheight);
      dojo.connect(this.playerHand, 'onChangeSelection', this, 'onPlayerHandSelectionChanged');

      // Only one item can be selected at a time
      this.playerHand.setSelectionMode(1);

      // tiles per row in the sprite image
      this.playerHand.image_items_per_row = this.tiles.length;

      // Create tile types
      for(var i=0;i<this.colors.length;i++) {
        var color=this.colors[i];
        for(var j=0;j<this.tiles.length;j++) {
          var tileName = this.tiles[j];
          // Build tile type id
          var stockid = this.getTileStockId(color, tileName);
//          console.log(color + ' ' + tileName + ' is ' + stockid);
          this.playerHand.addItemType(stockid, stockid,
           g_gamethemeurl + 'img/tiles.png', stockid);
        }
      }

      // Set up player areas
      this.colorsByPlayerId = {}
      for(var player_id in gamedatas.players) {
        var player = gamedatas.players[player_id];
        this.colorsByPlayerId[player_id] = this.colorsByHex[player.color];
      }

      // Current player's hand of tiles
      for (var deckid in gamedatas.hand) {
        var tile = gamedatas.hand[deckid];
        var typeid = tile.type_arg;
        var name = this.tiles[typeid];
        var stockid = this.getTileStockId(this.myColor, name);
        this.playerHand.addToStockWithId(stockid, deckid);
      }

      // Fill in board squares
      for (var deckid in gamedatas.board) {
        var boardTile = gamedatas.board[deckid];
        var location = boardTile.location_arg;
        var color = this.colorsByHex[gamedatas.players[boardTile.type].color];
        var name = this.tiles[boardTile.type_arg];

        var stockid = this.getTileStockId(
         color, name);
        this.setBoardSquareTile(stockid, $('square_' + location));
      }

      // Set up onClick action for the board squares
      this.addEventToClass('square', 'onclick', 'onClickBoardSquare');

      // Set up notification handlers
      this.setupNotifications();
    },
       

    ///////////////////////////////////////////////////
    //// Game & client states
    //
    // onEnteringState: this method is called each time we are entering into
    // a new game state.
    //
    //  You can use this method to perform some user interface changes at
    //  this moment.
    //
    onEnteringState: function(stateName, args) {
      console.log('Entering state: ' + stateName);

      switch(stateName) {
        /* Example:
        case 'myGameState':
          // Show some HTML block at this game state
          dojo.style( 'my_html_block_id', 'display', 'block' );
          break;
        */
        case 'dummy':
        break;
      }
    },

    // onLeavingState: this method is called each time we are leaving a game
    // state.
    //
    // You can use this method to perform some user interface changes at
    // this moment.
    //
    onLeavingState: function(stateName) {
      console.log( 'Leaving state: '+stateName );

      switch(stateName) {
        /* Example:
        case 'myGameState':
          // Hide the HTML block we are displaying only during this game state
          dojo.style( 'my_html_block_id', 'display', 'none' );
          break;
         */
        case 'dummy':
        break;
      }
    }, 

    // onUpdateActionButtons: in this method you can manage "action buttons"
    // that are displayed in the action status bar (ie: the HTML links in
    // the status bar).
    //        
    onUpdateActionButtons: function(stateName, args) {
      console.log('onUpdateActionButtons: ' + stateName);
      if (this.isCurrentPlayerActive()) {
        switch(stateName) {
          /* Example:
          case 'myGameState':
            // Add 3 action buttons in the action status bar:
            this.addActionButton( 'button_1_id', _('Button 1 label'), 'onMyMethodToCall1' ); 
            this.addActionButton( 'button_2_id', _('Button 2 label'), 'onMyMethodToCall2' ); 
            this.addActionButton( 'button_3_id', _('Button 3 label'), 'onMyMethodToCall3' ); 
          break;
          */
        }
      }
    },

    ///////////////////////////////////////////////////
    //// Utility methods

    /*
    Here you can defines some utility methods that you can use everywhere in
    your javascript script.
    */

    // Get tile stock identifier based on its color and name
    getTileStockId: function(color, name) {
      return (this.colors.indexOf(color) * this.tiles.length)
       + this.tiles.indexOf(name);
    },

    // Reverse of above
    tileStockIdToColorAndType: function(id) {
      return [Math.floor(id / this.tiles.length), (id % this.tiles.length)];
    },

    // Stock does this calculation for us when we can use it, but sometimes
    // we need to display bare tiles
    tileStockIdToSpriteOffset: function(id) {
      var colorAndType = this.tileStockIdToColorAndType(id);
      var ret = '-' + (colorAndType[1] * 100) + '% -' + (colorAndType[0] * 100) + '%';
      return ret;
    },

    // Place a tile onto a square of the board
    setBoardSquareTile: function(stockid, square) {
      dojo.addClass(square, 'type_' + stockid);
      dojo.style(square, 'background-position',
       this.tileStockIdToSpriteOffset(stockid));
      dojo.style(square, 'background-image',
       'url(' + g_gamethemeurl + 'img/tiles.png)');
    },

    clearHighlightedSquares: function() {
      dojo.query('.possibleMove').removeClass('possibleMove');
    },

    highlightPossibleMoves: function(stockid) {
      var colorAndType = this.tileStockIdToColorAndType(stockid);
      var type = colorAndType[1];
      var name = this.tiles[type];
      if (name == '$') {
        dojo.query('.square').addClass('possibleMove');
      } else {
        dojo.query('.kind_' + name).addClass('possibleMove');
      }
    },

    ///////////////////////////////////////////////////
    //// Player's action
        
    /*
    Here, you are defining methods to handle player's action (ex: results of
    mouse click on game objects).
            
    Most of the time, these methods:
      _ check the action is possible at this game state.
      _ make a call to the game server
        
    */
    onClickBoardSquare: function(evt) {
      dojo.stopEvent(evt);
      var action = 'playTile';
      var selected = this.playerHand.getSelectedItems();
      
      if (selected.length == 0 // No tile selected, so can't play one
       || ! this.checkAction(action, true) // Not your turn
       || ! dojo.hasClass(evt.target, 'possibleMove')) { // The square clicked isn't a valid placement of this tile
        return;
      }
      var stockid = selected[0].type;
      var deckid = selected[0].id;

      this.playerHand.removeFromStock(stockid, evt.target);
      this.setBoardSquareTile(stockid, evt.target);
      this.clearHighlightedSquares();
      var location = evt.target.id.split('_', 2)[1];
      this.ajaxcall("/" + this.game_name + "/" + this.game_name + "/" + action + ".html", {
       tile:deckid,
       location:location
      }, this, function(result) {});
    },

    // When the player selects a tile, highlight where it can go
    onPlayerHandSelectionChanged: function() {
      var selected = this.playerHand.getSelectedItems();

      if (selected.length == 0) {
        this.clearHighlightedSquares();
      } else {
        var stockid = selected[0].type;
        this.highlightPossibleMoves(stockid);
      }
    },

    ///////////////////////////////////////////////////
    //// Reaction to cometD notifications
    /*
    setupNotifications:
            
    In this method, you associate each of your game notifications with your
    local method to handle it.
            
    Note: game notification names correspond to "notifyAllPlayers" and
    "notifyPlayer" calls in your uptown.game.php file.
    */

    setupNotifications: function() {
      console.log( 'notifications subscriptions setup' );
      dojo.subscribe('tileDrew', this, "notif_tileDrew");
      dojo.subscribe('playTile', this, "notif_playTile");
    },

    // We just drew a new tile
    notif_tileDrew: function(notif) {
      console.log("notif_tileDrew");
      console.log(notif);
      var tile = notif.args.tile;
      var typeid = this.tiles[tile.type_arg];
      var name = this.tiles[typeid];
      var stockid = this.getTileStockId(this.myColor, name);
      var deckid = notif.args.id;
      this.playerHand.addToStockWithId(stockid, deckid);
    },

    // Someone just played a tile
    notif_playTile: function(notif) {
      if (notif.args.player_id == this.myId) {
        // If this is our own play, we already updated the board
        return;
      }
      var typeid = notif.args.tile_type;
      var color = this.colorsByPlayerId[notif.args.player_id];
      var name = this.tiles[typeid];
      var stockid = this.getTileStockId(color, name);
      this.setBoardSquareTile(stockid, $('square_' + notif.args.location));
    }

  });             
});
