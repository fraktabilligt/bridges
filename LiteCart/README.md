# Instructions

1. Download our latest build [here](https://github.com/fraktabilligt/bridges/archive/master.zip).

2. Upload the contents of the folder public_html/* to the corresponding path of your e-commerce platform installation.

3. Open ~/shiplink.php in your favourite editor and change the following configuration values:

  - username = HTTP Auth Digest username made up by you
  - password = HTTP Auth Digest password made up by you
  - secret_key = A secret string made up by you

4. Sign in to your account on Shiplink and edit your account settings.

   In the list of import gateway modules configure the Generic Bridge accoarding to your bridge configuration in the previous step.

   The URL to the bridge is http://www.yoursite.com/shiplink.php
