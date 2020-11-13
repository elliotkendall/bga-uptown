# bga-uptown
Digital adaptation of Uptown for Board Game Area

Tile IDs:

* TypeID: an identifier of the kind of tile, e.g. A, 4, lamp, etc. Integer
between 0 (A) and 27 ($).  This is also the type_arg of tiles in the PHP
Deck object.

* StockID: an identifier of the kind of tile and the color of tile, e.g. 
blue A, red 4, yellow lamp, etc.  Integer between 0 (blue A) and 139 (yellow
$).  Used in the javascript Stock component.  Also used (confusingly) in the
"type_XX" class in on div.square DOM elements.

* DeckID: a unique identifer of a card in the PHP Deck object. An integer
with unpredictable value.

* Name: the text name of a tile, e.g. "A", "4", "lamp". 
