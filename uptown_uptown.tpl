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

<div id="uptown_board">
  <!-- BEGIN square -->
  <div id="uptown_square_{ID}"
   class="uptown_square uptown_kind_{NUMBER} uptown_kind_{LETTER} uptown_kind_{SYMBOL}"
   style="left: {LEFT}px; top: {TOP}px;"></div>
  <!-- END square -->
</div>

<div id="uptown_mytiles_wrap" class="whiteblock">
  <h3>{MY_TILES}</h3>
  <div id="uptown_last_turn"></div>
  <div class="uptown_player_hand" id="uptown_player_hand_self">
  </div>
  <div class="uptown_player_captured" id="uptown_player_captured_self"></div>
  <div class="uptown_player_draw_pile" id="uptown_player_draw_pile_self"></div>
</div> <!-- id="uptown_mytiles_wrap" -->

<div id="uptown_player_areas">
  <!-- BEGIN uptown_player_area -->
  <div class="uptown_player_area whiteblock">
    <div class="uptown_player_name" style="background-color:#{PLAYER_COLOR}">{PLAYER_NAME}</div>
    <div class="uptown_player_hand" id="uptown_player_hand_{PLAYER_ID}"></div>
    <div class="uptown_player_captured" id="uptown_player_captured_{PLAYER_ID}"></div>
    <div class="uptown_player_draw_pile" id="uptown_player_draw_pile_{PLAYER_ID}"></div>
  </div> <!-- class="uptown_player_area whiteblock" -->
  <!-- END uptown_player_area -->
</div> <!-- id="uptown_player_areas" -->

<script type="text/javascript">

// Javascript HTML templates

var jstpl_player_board = '\<div class="uptown_cp_board">\
    <div id="uptown_captureicon_p${id}"\
    class="uptown_capture_tooltip uptown_captureicon uptown_captureicon_${color}"></div>\
   <span id="uptown_capturecount_p${id}" class="uptown_capture_tooltip">${capturecount}</span>\
    <div id="uptown_drawpileicon_p${id}"\
    class="uptown_draw_tooltip uptown_drawpileicon uptown_drawpileicon_${color}"></div>\
   <span id="uptown_drawpilecount_p${id}" class="uptown_draw_tooltip">${deckcount}</span>\
</div>';

var jstpl_player_board_2p = '\<div class="uptown_cp_board">\
    <div id="uptown_captureicon_p${id}"\
    class="uptown_capture_tooltip uptown_captureicon uptown_captureicon_${color}"></div>\
   <span id="uptown_capturecount_p${id}" class="uptown_capture_tooltip">${capturecount}</span>\
    <div id="uptown_drawpileicon_p${id}"\
    class="uptown_draw_tooltip uptown_drawpileicon uptown_drawpileicon_${color}"></div>\
   <span id="uptown_drawpilecount_p${id}" class="uptown_draw_tooltip">${deckcount}</span>\
    <div id="uptown_drawpileicon_alt_p${id}"\
    class="uptown_draw_tooltip uptown_drawpileicon uptown_drawpileicon_${color2}"></div>\
   <span id="uptown_drawpilecount_alt_p${id}" class="uptown_draw_tooltip">${deckcount_alt}</span>\
</div>';

var jstpl_log_icon = '\<div \
 class="uptown_log_icon" style="background-position: ${offset};">\
</div>';

</script>  


{OVERALL_GAME_FOOTER}
