<?php

namespace App\Entity\Report;


class Bilanca
{
	/**
	 * 1 	001 	SREDSTVA (2+12+25)
	 */
	public $p001;
	/**
	 * 2 	002 	A. DOLGOROČNA SREDSTVA (3+6+7+8+11)
	 */
	public $p002;
	/**
	 * 3 	003 	I. Neopredmetena sredstva in dolgoročne aktivne časovne razmejitve (4+5)
	 */
	public $p003;
	/**
	 * 4 	004 	1. Neopredmetena sredstva
	 */
	public $p004 = 0;
	/**
	 * 5 	009 	2. Dolgoročne aktivne časovne razmejitve
	 */
	public $p009 = 0;
	/**
	 * 6 	010 	II. Opredmetena osnovna sredstva
	 */
	public $p010 = 0;
	/**
	 *7 	018 	III. Naložbene nepremičnine
	 */
	public $p018 = 0;
	/**
	 *8 	019 	IV. Dolgoročne finančne naložbe (9+10)
	 */
	public $p019;
	/**
	*9 	020 	1. Dolgoročne finančne naložbe, razen posojil
	*/
	public $p020 = 0;
	/**
	 *10 	024 	2. Dolgoročna posojil
	 */
	public $p024 = 0;
	/**
	 *11 	027 	V. Dolgoročne poslovne terjatve
	 */
	public $p027 = 0;
	/**
	 *12 	032 	B. KRATKOROČNA SREDSTVA (13+14+20+23+24)
	 */
	public $p032;
	/**
	 *13 	033 	I. Sredstva (skupine za odtujitev) za prodajo
	 */
	public $p033 = 0;
	/**
	 *14 	034 	II. Zaloge (15+16+17+18+19)
	 */
	public $p034;
	/**
	 *15 	035 	1. Material
	 */
	public $p035 = 0;
	/**
	 *16 	036 	2. Nedokončana proizvodnja
	 */
	public $p036 = 0;
	/**
	 *17 	037 	3. Proizvodi
	 */
	public $p037 = 0;
	/**
	 *18 	038 	4. Trgovsko blago
	 */
	public $p038 = 0;
	/**
	 *19 	039 	5. Predujmi za zaloge
	 */
	public $p039 = 0;
	/**
	 *20 	040 	III. Kratkoročne finančne naložbe (21+22)
	 */
	public $p040;
	/**
	 *21 	041 	1. Kratkoročne finančne naložbe, razen posojil
	 */
	public $p041 = 0;
	/**
	 *22 	045 	2. Kratkoročna posojila
	 */
	public $p045 = 0;
	/**
	 *23 	048 	IV. Kratkoročne poslovne terjatve
	 */
	public $p048 = 0;
	/**
	 *24 	052 	V. Denarna sredstva
	 */
	public $p052 = 0;
	/**
	 *25 	053 	C. KRATKOROČNE AKTIVNE ČASOVNE RAZMEJITVE
	 */
	public $p053 = 0;
	/**
	 *26 	054 	Zabilančna sredstva
	 */
	public $p054 = 0;
	/**
	 *27 	055 	OBVEZNOSTI DO VIROV SREDSTEV (28+36+39+42+46)
	 */
	public $p055;
	/**
	 *28 	056 	A. PODJETNIKOV KAPITAL (29+30+31+32+33+34-35)
	 */
	public $p056;
	/**
	 *29 	058 	I. Začetni podjetnikov kapital
	 */
	public $p058 = 0;
	/**
	 *30 	060a 	II. Prenosi stvarnega premoženja med opravljanjem dejavnosti
	 */
	public $p060a = 0;
	/**
	 *31 	060b 	III. Pritoki in odtoki denarnih sredstev
	 */
	public $p060b = 0;
	/**
	 *32 	067 	IV. Revalorizacijske rezerve
	 */
	public $p067 = 0;
	/**
	 *33 	301 	V. Rezerve, nastale zaradi vrednotenja po pošteni vrednosti
	 */
	public $p301 = 0;
	/**
	 *34 	070 	VI. Podjetnikov dohodek
	 */
	public $p070 = 0;
	/**
	 *35 	071 	VII. Negativni poslovni izid
	 */
	public $p071 = 0;
	/**
	 *36 	072 	B. REZERVACIJE IN DOLGOROČNE PASIVNE ČASOVNE RAZMEJITVE (37+38)
	 */
	public $p072;
	/**
	 *37 	073 	1. Rezervacije
	 */
	public $p073 = 0;
	/**
	 *38 	074 	2. Dolgoročne pasivne časovne razmejitve
	 */
	public $p074 = 0;
	/**
	 *39 	075 	C. DOLGOROČNE OBVEZNOSTI (40+41)
	 */
	public $p075;
	/**
	 *40 	076 	I. Dolgoročne finančne obveznosti
	 */
	public $p076 = 0;
	/**
	 *41 	080 	II. Dolgoročne poslovne obveznosti
	 */
	public $p080 = 0;
	/**
	 *42 	085 	Č. KRATKOROČNE OBVEZNOSTI (43+44+45)
	 */
	public $p085;
	/**
	 *43 	086 	I. Obveznosti, vključene v skupine za odtujitev
	 */
	public $p086 = 0;
	/**
	 *44 	087 	II. Kratkoročne finančne obveznosti
	 */
	public $p087 = 0;
	/**
	 *45 	091 	III. Kratkoročne poslovne obveznosti
	 */
	public $p091 = 0;
	/**
	 *46 	095 	D. KRATKOROČNE PASIVNE ČASOVNE RAZMEJITVE
	 */
	public $p095 = 0;
	/**
	 *47 	096 	Zabilančne obveznosti
	 */
	public $p096 = 0;
	
	public function calculate() : void
	{
		$this->p085 = $this->p086 + $this->p087 + $this->p091;
		
		$this->p075 = $this->p076 + $this->p080;
		
		$this->p072 = $this->p073 + $this->p074;
		
		$this->p056 = $this->p058 + $this->p060a + $this->p060b + $this->p067 + $this->p301 + $this->p070 - $this->p071;
		
		$this->p055 = $this->p056 + $this->p072 + $this->p075 + $this->p085 + $this->p095;
		
		$this->p040 = $this->p041 + $this->p045;
		
		$this->p034 = $this->p035 + $this->p036 + $this->p037 + $this->p038 + $this->p039;
		
		$this->p032 = $this->p033 + $this->p034 + $this->p040 + $this->p048 + $this->p052;
		
		$this->p019 = $this->p020 + $this->p024;

		$this->p003 = $this->p004 + $this->p009;
		
		$this->p002 = $this->p003 + $this->p010 + $this->p018 + $this->p019 + $this->p027;
		
		$this->p001 = $this->p002 + $this->p032 + $this->p053;
		
		$interm = $this->p001 - $this->p055;
		$this->p070 = $interm > 0 ? $interm : 0;
		$this->p071 = $interm < 0 ? -$interm : 0;
		
		$this->p056 = $this->p058 + $this->p060a + $this->p060b + $this->p067 + $this->p301 + $this->p070 - $this->p071;
		
		$this->p055 = $this->p056 + $this->p072 + $this->p075 + $this->p085 + $this->p095;
	}
}
