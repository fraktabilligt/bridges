	    __    _ __       ______           __ 
	   / /   (_) /____  / ____/___ ______/ /_
	  / /   / / __/ _ \/ /   / __ `/ ___/ __/
	 / /___/ / /_/  __/ /___/ /_/ / /  / /_  
	/_____/_/\__/\___/\____/\__,_/_/   \__/  
	                      www.litecart.net
	                                         
################
# Instructions #
################

1. Always backup data before making changes to your store.

2. Upload the contents of the folder public_html/* to the corresponding path of your LiteCart installation.

3. Open ~/fraktabilligt.php in your favourite editor and change the following configuration values:

    name = The name of your bridge
    version = The API version of the bridge
    username = HTTP Auth username
    password = HTTP Auth password
    secret_key = A secret string made up by you
    
4. Sign in to your account on Fraktabilligt and edit your account settings.

   In the list of import gateway modules configure the Generic Bridge accoarding to your bridge configuration in the step above.
   
   The URL to the bridge is http://www.yoursite.com/fraktabilligt.php
 