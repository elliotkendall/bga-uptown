{OVERALL_GAME_HEADER}

<!-- 
--------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- Uptown implementation : © Elliot Kendall <elliotkendall@gmail.com>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-------
-->

<div id="board">
  <!-- BEGIN square -->
  <div id="square_{ID}"
   class="square kind_{NUMBER} kind_{LETTER} kind_{SYMBOL}"
   style="left: {LEFT}px; top: {TOP}px;"></div>
  <!-- END square -->
</div>

<div id="mytiles_wrap" class="whiteblock">
  <h3>{MY_TILES}</h3>
  <div class="player_hand" id="player_hand_self">
  </div>
  <div class="player_captured" id="player_captured_self"></div>
  <div class="player_draw_pile" id="player_draw_pile_self"></div>
</div> <!-- id="mytiles_wrap" -->

<div id="player_areas">
  <!-- BEGIN player_area -->
  <div class="player_area whiteblock">
    <div class="player_name" style="color:#{PLAYER_COLOR}">{PLAYER_NAME}</div>
    <div class="player_hand" id="player_hand_{PLAYER_ID}"></div>
    <div class="player_captured" id="player_captured_{PLAYER_ID}"></div>
    <div class="player_draw_pile" id="player_draw_pile_{PLAYER_ID}"></div>
  </div> <!-- class="player_area whiteblock" -->
  <!-- END player_area -->
</div> <!-- id="player_areas" -->

<script type="text/javascript">

// Javascript HTML templates

var jstpl_player_board = '\<div class="cp_board">\
    <div id="captureicon_p${id}"\
    class="uptown_captureicon uptown_captureicon_${color}"></div>\
   <span id="capturecount_p${id}">${capturecount}</span>\
    <div id="drawpileicon_p${id}"\
    class="uptown_drawpileicon uptown_drawpileicon_${color}"></div>\
   <span id="drawpilecount_p${id}">${deckcount}</span>\
</div>';

</script>  


{OVERALL_GAME_FOOTER}
