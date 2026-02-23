<?php


class ShipsGo_API
{
	
	private $authCode = '';
	
	public function __construct( String $authCode )
	{
		$this -> authCode = $authCode;
	}

	private function getData( String $urlExt, Array $postData = array() )
	{
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 1 );
		curl_setopt( $ch, CURLOPT_URL, 'https://shipsgo.com/api/ContainerService/' . $urlExt );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );

		if( !empty( $postData ) )
		{
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $postData ) );
		}

		curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Accept: text/json' ) );

		$response = curl_exec( $ch );
		curl_close( $ch );

		if( preg_match( '/<p>HTTP Error 400\. The request is badly formed\.<\/p>/', $response ) )
		{
			$response = array( 'Message' => 'Bad Request' );
		}else
		{
			$response = json_decode($response, true);
			
			if( !is_array( $response ) )
			{
				$requestId = $response;
				$response = array( 'RequestId' => $requestId );
			}else
			{
				if( isset( $response['Message'] ) )
				{
					$messageArray = explode( "\n", $response['Message'] );

					if( count( $messageArray ) > 1 )
					{
						unset( $messageArray[( count( $messageArray ) - 1 )] );
						$response['Message'] = $messageArray;
					}
				}
			}
		}

		if( !is_array( $response ) ){ $response = array( 'Message' => 'Something Went Wrong.' ); }

		return $response;

	}
	
	public function GetShippingLineList()
	{
		return $this -> getData( 'GetShippingLineList' );
	}

	public function GetContainerInfo( $searchParam )
	{
		return $this -> getData( 'GetContainerInfo/?authCode=' . $this -> authCode . '&requestId=' . $searchParam );
	}

	public function PostContainerInfo( String $containerNumber, String $shippingLine, $emails = '', String $referenceNo = '' )
	{
		$postData = array(
			'authCode' 			=> $this -> authCode,
			'containerNumber'	=> $containerNumber,
			'shippingLine' 		=> $shippingLine
		);

		if( $emails == '' && $referenceNo == '' )
		{
			return $this -> getData( 'PostContainerInfo', $postData );
		}else
		{
			$postData['emailAddress'] = ( is_array( $emails ) ) ? implode( ',', $emails ) : str_replace( ' ', '', $emails );
			$postData['referenceNo'] = $referenceNo;

			return $this -> getData( 'PostCustomContainerForm', $postData );
		}
	}

	public function PostContainerInfoWithBl( String $containerNumber, $blReference, String $shippingLine, $emails = '', String $referenceNo = '' )
	{
		$postData = array(
			'authCode' 			=> $this -> authCode,
			'containerNumber'	=> $containerNumber,
			'shippingLine' 		=> $shippingLine,
			'containersCount' 	=> '',
		);

		if( is_array( $blReference ) )
		{
			$postData['blContainersRef'] = $blReference[0];
			$postData['containersCount'] = ( isset( $blReference[1] ) ) ? $blReference[1] : '';
		}else
		{
			$postData['blContainersRef'] = $blReference;
		}

		if( $emails == '' && $referenceNo == '' )
		{
			return $this -> getData( 'PostContainerInfoWithBl', $postData );
		}else
		{
			$postData['emailAddress'] = ( is_array( $emails ) ) ? implode( ',', $emails ) : str_replace( ' ', '', $emails );
			$postData['referenceNo'] = $referenceNo;

			return $this -> getData( 'PostCustomContainerFormWithBl', $postData );
		}
	}
	
}

?>