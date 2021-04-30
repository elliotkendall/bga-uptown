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
        "94bec6": "blue",
        "9ab79a": "green",
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
      if (!this.isSpectator) {
        this.myColor = this.colorsByHex[gamedatas.players[gamedatas.my_player_id].color];
        this.myId = gamedatas.my_player_id;

        // Player hand
        this.playerHand = new ebg.stock();
        this.playerHand.create(this, $('uptown_player_hand_self'),
         this.tilewidth, this.tileheight);
        this.playerHand.setSelectionAppearance('class');
        this.playerHand.extraClasses='uptown_player_hand_self_item';
        dojo.connect(this.playerHand, 'onChangeSelection', this, 'onPlayerHandSelectionChanged');

        // Allow one item to be selected if it's our turn
        if (this.checkAction('playTile', true)) {
          this.playerHand.setSelectionMode(1);
        } else {
          this.playerHand.setSelectionMode(0);
        }

        // tiles per row in the sprite image
        this.playerHand.image_items_per_row = this.tiles.length;
      } else {
        this.myId = 0;
      }

      // Set up player areas
      this.colorsByPlayerId = {};
      this.playerIdsByColor = {};
      this.captureAreas = {};
      this.hands = {};
      for(var player_id in gamedatas.players) {
        var player = gamedatas.players[player_id];
        this.colorsByPlayerId[player_id] = this.colorsByHex[player.color];
        this.playerIdsByColor[this.colorsByHex[player.color]] = player_id;
        var ca = new ebg.stock();
        ca.extraClasses='uptown_captured_tile';
        if (player_id == this.myId) {
          var target = $('uptown_player_captured_self');
        } else {
          var target = $('uptown_player_captured_' + player_id);
          var hand = new ebg.stock();
          hand.create(this, $('uptown_player_hand_' + player_id), this.tilewidth, this.tileheight);
          hand.extraClasses='uptown_player_hand_item';
          // Don't allow selection
          hand.setSelectionMode(0);
          hand.image_items_per_row = 1;
          ca.setSelectionMode(0);
          var id = this.colors.indexOf(this.colorsByPlayerId[player_id])
          hand.addItemType(id, id,
           g_gamethemeurl + 'img/colors.png', id);
          for(var i=0;i<player.handcount;i++) {
            hand.addToStock(id);
          }
          this.hands[player_id] = hand;
        }
        ca.create(this, target, this.tilewidth, this.tileheight);
        // Don't allow selection
        ca.setSelectionMode(0);
        // tiles per row in the sprite image
        ca.image_items_per_row = this.tiles.length;
        this.captureAreas[player_id] = ca;

        // Capture count in the player panel
        var player_board_div = $('player_board_'+player_id);
        player.capturecount = Object.keys(player.captured).length;
        dojo.place( this.format_block('jstpl_player_board', player ), player_board_div);
        this.addTooltipToClass("uptown_capture_tooltip", _("Number of opponents' tiles captured"), '');
        this.addTooltipToClass("uptown_draw_tooltip", _("Number of tiles left to draw"), '');
      }

      // Create tile types
      for(var i=0;i<this.colors.length;i++) {
        var color=this.colors[i];
        for(var j=0;j<this.tiles.length;j++) {
          var tileName = this.tiles[j];
          // Build tile type id
          var stockid = this.getTileStockId(color, tileName);
          if (!this.isSpectator) {
            // Add it to our hand
            this.playerHand.addItemType(stockid, stockid,
             g_gamethemeurl + 'img/tiles.webp', stockid);
          } 
          // Add it to capture areas
          for(var player_id in gamedatas.players) {
            this.captureAreas[player_id].addItemType(stockid, stockid,
             g_gamethemeurl + 'img/tiles.webp', stockid);
          }
        }
      }

      // Populate captured tiles
      for(var player_id in gamedatas.players) {
        var player = gamedatas.players[player_id];
        for(deckid in player.captured) {
          var tile = player.captured[deckid];
          var name = this.tiles[tile.type_arg];
          var color = this.colorsByPlayerId[tile.type];
          var stockid = this.getTileStockId(color, name);
          this.captureAreas[player_id].addToStockWithId(stockid, deckid);
        }
      }

      if (!this.isSpectator) {
        // Populate current player's hand of tiles
        for (var deckid in gamedatas.hand) {
          var tile = gamedatas.hand[deckid];
          var typeid = tile.type_arg;
          var name = this.tiles[typeid];
          var stockid = this.getTileStockId(this.myColor, name);
          this.playerHand.addToStockWithId(stockid, deckid);
        }
      }

      // Display an error when controls are clicked out of turn
      dojo.query('.uptown_player_hand_self_item, .uptown_square')
       .connect('onclick', this, function(evt) {
        if (! this.checkAction('playTile', true)) {
          this.showMessage("It's not your turn", "error")
        }
      });

      // Fill in board squares
      for (var deckid in gamedatas.board) {
        var boardTile = gamedatas.board[deckid];
        var location = boardTile.location_arg;
        var color = this.colorsByHex[gamedatas.players[boardTile.type].color];
        var name = this.tiles[boardTile.type_arg];

        var stockid = this.getTileStockId(
         color, name);
        this.setBoardSquareTile(stockid, $('uptown_square_' + location), color);
      }

      // Configure protected squares
      for (var i in gamedatas.protected) {
        dojo.query('#uptown_square_' + gamedatas.protected[i]).addClass('uptown_protected');
      }

      // Set up onClick action for the board squares
      this.addEventToClass('uptown_square', 'onclick', 'onClickBoardSquare');

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
      // Make hand tiles clickable if it's our turn
      if (!this.isSpectator) {
        // For some unknown reason checkAction() sometimes returns false
        // here on the final turn of the game. Comparing active ID to
        // our ID seems a sufficient workaround
        if (this.checkAction('playTile', true)
         || (stateName == "playerTurn" && args.active_player == this.myId)) {
          if (this.getDeckCount(args.active_player) === "0") {
            this.showMessage("This is your last turn!", "info");
          }
          this.playerHand.setSelectionMode(1);
        } else {
          this.playerHand.setSelectionMode(0);
        }
      }
    },

    // onLeavingState: this method is called each time we are leaving a game
    // state.
    //
    // You can use this method to perform some user interface changes at
    // this moment.
    //
    onLeavingState: function(stateName) {

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

    /*
    Override this function to inject html into log items.  This is a
    built-in BGA method.
    */
    format_string_recursive: function(log, args) {
      try {
        if (log && args && !args.processed) {
          args.processed = true;
          var tile_names_to_player_ids = {
           "tile_name": "player_id",
           "captured_tile_name": "capture_target_id"
          };
          for (const [tilename, playerid] of Object.entries(tile_names_to_player_ids)) {
            if (tilename in args) {
              var color = this.colorsByPlayerId[args[playerid]];
              var stockid = this.getTileStockId(color, args[tilename]);
              args[tilename] = this.format_block('jstpl_log_icon', {
                "offset" : this.tileStockIdToSpriteOffset(stockid)
              });
            }
          }
        }
      } catch (e) {
        console.error(log,args,"Exception thrown", e.stack);
      }
      return this.inherited(arguments);
    },

    // Like scoreCtrl for the captured tiles count in the player panel
    incrementCaptureCount: function(player_id) {
      var span = dojo.query("#uptown_capturecount_p" + player_id);
      span.text(parseInt(span.text()) + 1);
    },

    // Like scoreCtrl for the draw pile size in the player panel
    setDeckCount: function(player_id, count) {
      var span = dojo.query("#uptown_drawpilecount_p" + player_id);
      span.text(count);
    },

    getDeckCount: function(player_id) {
      var span = dojo.query("#uptown_drawpilecount_p" + player_id);
      return span.text();
    },

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
    setBoardSquareTile: function(stockid, square, color) {
      // Remove any color or kind classes
      square.classList.forEach(function(cls) {
        if (cls.startsWith('uptown_color_') || cls.startsWith('uptown_type_')) {
          dojo.removeClass(square, cls);
        }
      });
      dojo.addClass(square, 'uptown_color_' + color);
      dojo.addClass(square, 'uptown_type_' + stockid);
      dojo.addClass(square, 'uptown_board_tile');
      dojo.style(square, 'background-position',
       this.tileStockIdToSpriteOffset(stockid));
      dojo.style(square, 'background-image',
       'url(' + g_gamethemeurl + 'img/tiles.webp)');
    },

    clearHighlightedSquares: function() {
      dojo.query('.uptown_possibleMove').removeClass('uptown_possibleMove');
    },

    highlightPossibleMoves: function(stockid) {
      var colorAndType = this.tileStockIdToColorAndType(stockid);
      var type = colorAndType[1];
      var name = this.tiles[type];
      var squares = dojo.NodeList();
      var query;
      if (name == '$') {
        query = '.uptown_square';
      } else {
        query = '.uptown_kind_' + name;
      }
      var cl;
      var colorclass = 'uptown_color_' + this.myColor;
      dojo.query(query).forEach(function(node) {
        cl = node.classList;
        if (! (cl.contains('uptown_protected') || cl.contains(colorclass))) {
          squares.push(node);
        }
      });
      squares.addClass('uptown_possibleMove');
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
       || ! dojo.hasClass(evt.target, 'uptown_possibleMove')) { // The square clicked isn't a valid placement of this tile
        return;
      }

      // Don't actually move anything around. We'll wait for the
      // notification from the server to do that

      var deckid = selected[0].id;
      var location = evt.target.id.split('_')[2];
      this.ajaxcall("/" + this.game_name + "/" + this.game_name + "/" + action + ".html", {
       lock:true,
       tile:deckid,
       location:location
      }, this, function(result) {});


    },

    // When the player selects a tile, highlight where it can go
    onPlayerHandSelectionChanged: function() {
      var selected = this.playerHand.getSelectedItems();

      if (selected.length != 1) {
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
      dojo.subscribe('drawTile', this, "notif_drawTile");
      dojo.subscribe('drawTileOther', this, "notif_drawTileOther");
      dojo.subscribe('playTile', this, "notif_playTile");
    },

    // We just drew a new tile
    notif_drawTile: function(notif) {
      var typeid = notif.args.tile;
      var name = this.tiles[typeid];
      var stockid = this.getTileStockId(this.myColor, name);
      var deckid = notif.args.id;
      var src = "uptown_drawpilecount_p" + this.myId;
      this.playerHand.addToStockWithId(stockid, deckid, src);
    },

    // Someone else just drew a new tile
    notif_drawTileOther: function(notif) {
      var player_id = notif.args.who;
      // If we're the one who drew a tile, we don't need to do anything
      if (player_id === this.myId) {
        return;
      }
      var id = this.colors.indexOf(this.colorsByPlayerId[player_id])
      var src = "uptown_drawpilecount_p" + player_id;
      this.hands[player_id].addToStock(id, src);
    },

    // Someone just played a tile
    notif_playTile: function(notif) {
      var typeid = notif.args.tile_type;
      var player_id = notif.args.player_id;
      var color = this.colorsByPlayerId[player_id];
      var name = this.tiles[typeid];
      var stockid = this.getTileStockId(color, name);
      var location = notif.args.location;
      var locationID = 'uptown_square_' + location;
      var locationDOM = $(locationID);

      var typeclass = Array.from(locationDOM.classList)
       .find(function(i){return i.startsWith('uptown_type_')});
      if (typeclass) {
        // There was already a tile here, so treat this as a capture
        var captured_stockid = typeclass.split('_')[2];
        // Clear the existing tile
        dojo.removeClass(locationDOM, typeclass);
        // Add it to the correct player's capture area
        this.captureAreas[player_id].addToStock(captured_stockid, locationDOM);
        // Update the player's capture count in the panel
        this.incrementCaptureCount(player_id);
      }
      if (player_id == this.myId) {
        this.clearHighlightedSquares();

        var items = this.playerHand.getAllItems();
        var itemid = -1;
        for (const item of items) {
          if (item.type === stockid) {
            itemid = item.id;
            break;
          }
        }
        if (itemid === -1) {
          console.log('Could not find item in hand for stock id ' + stockid);
        } else {
          var divid = this.playerHand.getItemDivId(itemid);

          var animation_id = this.slideToObject(divid, locationID);
          dojo.connect(animation_id, 'onEnd', dojo.hitch(this, function() {
            this.setBoardSquareTile(stockid, locationDOM, color);
          }));
          animation_id.play();
        }
        this.playerHand.removeFromStock(stockid);
      } else {
        var id = this.colors.indexOf(this.colorsByPlayerId[player_id])

        var hand = this.hands[player_id].getAllItems();
        var selecteditem = hand[0];
        var selectedid = selecteditem.id;
        var divid = this.hands[player_id].getItemDivId(selectedid);

        var animation_id = this.slideToObject(divid, locationID);
        dojo.connect(animation_id, 'onEnd', dojo.hitch(this, function() {
          this.setBoardSquareTile(stockid, locationDOM, color);
        }));
        animation_id.play();
        this.hands[player_id].removeFromStock(id);
      }

      this.setDeckCount(player_id, notif.args.deckcount);
      // Update player scores
      for (var gpid in notif.args.groups) {
        this.scoreCtrl[gpid].setValue(-1 * notif.args.groups[gpid].length);
      }

      // Update protected squares
      dojo.query('.uptown_protected').removeClass('uptown_protected');
      for (var i in notif.args.protected) {
        dojo.query('#uptown_square_' + notif.args.protected[i]).addClass('uptown_protected');
      }
    }

  });             
});
