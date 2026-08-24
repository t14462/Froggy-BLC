<?php
/*
   @https://github.com/jakemadness/fisher-yates-seeded-shuffle
*/
class seeded_shuffle {


   private static function data_length($data) {
      return (is_array($data) ? count($data) : strlen($data));
   }


   public static function seed($data) {
      $seed   = '';
      $length = self::data_length($data);
      for ( $i = 0; $i < $length; ++$i ) {
         $seed .= mt_rand(0, 9);
      }
      return $seed;
   }


   public static function shuffle($data, $seed) {
      $length1 = self::data_length($data);
      $length2 = self::data_length($seed);
      for ( $i = 0; $i < $length1; ++$i ) {
         $swap        = $seed[$i % $length2] % $length1;
         $temp        = $data[$swap];
         $data[$swap] = $data[$i];
         $data[$i]    = $temp;
      }
      return $data;
   }


   public static function unshuffle($data, $seed) {
      $length1 = self::data_length($data);
      $length2 = self::data_length($seed);
      for ( $i = $length1 - 1; $i >= 0; --$i ) {
         $swap        = $seed[$i % $length2] % $length1;
         $temp        = $data[$swap];
         $data[$swap] = $data[$i];
         $data[$i]    = $temp;
      }
      return $data;
   }


}
?>
