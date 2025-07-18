<?php
class CSSParserToJson {
 //  private $hashCounter = 0;
 
    public function parse($cssString) {
        preg_match_all('/\.([a-zA-Z0-9_-]+)(:hover|:active|:focus)?\{([^}]*)\}/', $cssString, $matches, PREG_SET_ORDER);

        $classMap = [];
        $lastBaseClass = null;

        foreach ($matches as $match) {
            $classname = $match[1];
            $pseudo = $match[2] ?? null;
            $rules = $match[3];
            $state = $pseudo ? ltrim($pseudo, ':') : null;

            if ($state === null) {
                $lastBaseClass = $classname;
                $id = $this->generate_unique_hex_string(7);
                $classMap[$classname] = [
                    'id' => $id,
                    'type' => 'class',
                    'label' => $classname,
                    'variants' => []
                ];
            }

            $baseClass = $state ? $lastBaseClass : $classname;

            if (!isset($classMap[$baseClass])) {
                continue;
            }

            $rawProps = [];
			foreach (explode(';', $rules) as $rule) {
   				 if (trim($rule) === '') continue;
    				list($propName, $propValue) = array_map('trim', explode(':', $rule, 2));
    				$rawProps[$propName] = $propValue;
					}
			$props = $this->normalizeProps($rawProps);

            $classMap[$baseClass]['variants'][] = [
                'meta' => [
                    'breakpoint' => 'desktop',
                    'state' => $state
                ],
                'props' => $props
            ];
        }

        usort($classMap, function ($a, $b) {
            return strcmp($a['label'], $b['label']);
        });

        $result = [
            'items' => [],
            'order' => []
        ];

        foreach ($classMap as $item) {
            $result['items'][$item['id']] = $item;
            $result['order'][] = $item['id'];
        }

        return json_encode($result, JSON_UNESCAPED_SLASHES);
    }

	private function inferType($prop) { 
		if  ($prop=='font-size' || str_contains($prop, 'padding') || str_contains($prop, 'margin') || str_contains($prop, 'width') || str_contains($prop,'left') || str_contains($prop,'right') ||str_contains($prop,'top') || str_contains($prop,'bottom') || str_contains($prop,'spacing') || str_contains($prop,'height') || str_contains($prop,'gap') || str_contains($prop,'radius')) {
			return 'size'; 
		} 
		if  (str_contains($prop, 'box-shadow')){
			return 'box-shadow'; // assume custom CSS variables are size-related 
		} 
		if  ($prop=='z-index' || $prop=='column-count' ){
			return 'number'; 
		} 
	    if  (str_contains($prop, 'color')){ 
			return 'color'; 
		} 
		return 'string'; 
	}
	
	private function normalizeProps($rawProps) {
		$output = [];
		// Define logical mappings
		$logicalGroups = [
			'padding' => [
				'props' => [
					'padding-top' => 'block-start',
					'padding-bottom' => 'block-end',
					'padding-left' => 'inline-start',
					'padding-right' => 'inline-end'
				],
				'$$type' => 'dimensions'
			],
			'margin' => [
				'props' => [
					'margin-top' => 'block-start',
					'margin-bottom' => 'block-end',
					'margin-left' => 'inline-start',
					'margin-right' => 'inline-end'
				],
				'$$type' => 'dimensions'
			],
			'border-width' => [
				'props' => [
					'border-width-top' => 'block-start',
					'border-width-bottom' => 'block-end',
					'border-width-left' => 'inline-start',
					'border-width-right' => 'inline-end'
				],
				'$$type' => 'border-width'
			],
			'border-radius' => [
				'props' => [
					'border-top-left-radius' => 'start-start',
					'border-top-right-radius' => 'start-end',
					'border-bottom-left-radius' => 'end-start',
					'border-bottom-right-radius' => 'end-end'
				],
				'$$type' => 'border-radius'
			]
		];

		// Process logical groups
		foreach ($logicalGroups as $groupKey => $config) {
			$valueObj = []; 

			foreach ($config['props'] as $cssProp => $logicalKey) {
				if (isset($rawProps[$cssProp])) {

					$raw = trim($rawProps[$cssProp]);

					unset($rawProps[$cssProp]);
					// look for units like px 
					if (preg_match('/^(\\d+(?:\\.\\d+)?)(px|em|rem|%)$/', $raw, $m)) {  
						$valueObj[$logicalKey] = [
							'$$type' => 'size',
							'value' => [
								'size' => floatval($m[1]),   // use the number from say 10px
								'unit' => $m[2]              // use the unit px 
							]
						];
					} 
					// look for variables 
					if (preg_match('/^var\(--[a-zA-Z0-9-]+\)$/', $raw, $m)) {   
						$valueObj[$logicalKey] =  [
							'$$type' => 'size',
							'value' => [
								'size' => $raw,                 // use the full variable name
								'unit' => 'custom'
							]
						];
					}
					// if there is not match for either of these conditions, set it to null
					if  (!preg_match('/^(\\d+(?:\\.\\d+)?)(px|em|rem|%)$/', $raw) && !preg_match('/^var\(--[a-zA-Z0-9-]+\)$/', $raw)) {  
						$valueObj[$logicalKey] = null;
					}

				}  // end if isset
				else {
					// Always include the logical key, even if not set
					$valueObj[$logicalKey] = null;
				}
			}  // end foreach ($config['props'] as $cssProp => $logicalKey)
	//If at least one side is set, array_filter(...) returns a non-empty array, and !empty(...) is true, so the block will be added to the output.
	// If all values were null, then array_filter(...) returns an empty array, and !empty(...) is false, so the block will be skipped.
			   if (!empty(array_filter($valueObj, fn($v) => $v !== null))) {
				$output[$groupKey] = [
					'$$type' => $config['$$type'],
					'value' => $valueObj
				];
			}
		}  // end  foreach ($logicalGroups as $groupKey => $config) 

		// Handle all other raw props normally
		foreach ($rawProps as $name => $value) {
			$output[$name] = [
				'$$type' => $this->inferType($name),
				'value' => $this->normalizeValue($value)
			];
		}

		return $output;
	}

    private function normalizeValue($value) {
        if (preg_match('/^(\d+(?:\.\d+)?)(px|em|rem|%)$/', $value, $m)) {
            return [
                'size' => floatval($m[1]),
                'unit' => $m[2]
            ];
        }
        if (preg_match('/^var\(--[a-zA-Z0-9-]+\)$/', $value)) {   //check for variables 
            return [
                'size' => $value,
                'unit' => 'custom'
            ];
        }
        return trim($value, '"');
   }
	
	private function generate_unique_hex_string($length) {
		// Calculate the number of bytes needed for the desired length of the hexadecimal string
		$byte_length = ceil($length / 2);
		// Generate random bytes
		$random_bytes = random_bytes($byte_length);
		// Convert the random bytes to a hexadecimal string
		$hex_string = bin2hex($random_bytes);
		// Truncate the string to the desired length (if necessary)
		$hex_string = substr($hex_string, 0, $length);

		return 'g-' . $hex_string;
	}

}  //end class CSSParserToJson 
