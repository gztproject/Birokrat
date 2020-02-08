<?php

namespace App\Entity\Report;

class Turnout {
	/**
	 * 1 110 A.
	 * ČISTI PRIHODKI OD PRODAJE (2+3+4)
	 */
	public $p110;
	/**
	 * 2 111 I.
	 * Čisti prihodki od prodaje na domačem trgu
	 */
	public $p111 = 0;
	/**
	 * 3 115 II.
	 * Čisti prihodki od prodaje na trgu EU
	 */
	public $p115 = 0;
	/**
	 * 4 118 III.
	 * Čisti prihodki od prodaje na trgu izven EU
	 */
	public $p118 = 0;
	/**
	 * 5 121 B.
	 * POVEČANJE VREDNOSTI ZALOG PROIZVODOV IN NEDOKONČANE PROIZVODNJE
	 */
	public $p121 = 0;
	/**
	 * 6 122 C.
	 * ZMANJŠANJE VREDNOSTI ZALOG PROIZVODOV IN NEDOKONČANE PROIZVODNJE
	 */
	public $p122 = 0;
	/**
	 * 7 123 Č.
	 * USREDSTVENI LASTNI PROIZVODI IN LASTNE STORITVE
	 */
	public $p123 = 0;
	/**
	 * 8 124 D.
	 * SUBVENCIJE, DOTACIJE, REGRESI, KOMPENZACIJE IN DRUGI PRIHODKI, KI SO POVEZANI S POSLOVNIMI UČINKI
	 */
	public $p124 = 0;
	/**
	 * 9 125 E.
	 * DRUGI POSLOVNI PRIHODKI
	 */
	public $p125 = 0;
	/**
	 * 10 126 F.
	 * KOSMATI DONOS OD POSLOVANJA (1+5-6+7+8+9)
	 */
	public $p126;
	/**
	 * 11 127 G.
	 * POSLOVNI ODHODKI (12+16+21+25)
	 */
	public $p127;
	/**
	 * 12 128 I.
	 * Stroški blaga, materiala in storitev (13+14+15)
	 */
	public $p128;
	/**
	 * 13 129 1.
	 * Nabavna vrednost prodanega blaga in materiala
	 */
	public $p129 = 0;
	/**
	 * 14 130 2.
	 * Stroški porabljenega materiala
	 */
	public $p130 = 0;
	/**
	 * 15 134 3.
	 * Stroški storitev
	 */
	public $p134 = 0;
	/**
	 * 16 139 II.
	 * Stroški dela (17+18+19+20)
	 */
	public $p139;
	/**
	 * 17 140 1.
	 * Stroški plač
	 */
	public $p140 = 0;
	/**
	 * 18 141 2.
	 * Stroški pokojninskih zavarovanj
	 */
	public $p141 = 0;
	/**
	 * 19 142 3.
	 * Stroški drugih socialnih zavarovanj
	 */
	public $p142 = 0;
	/**
	 * 20 143 4.
	 * Drugi stroški dela
	 */
	public $p143 = 0;
	/**
	 * 21 144 III.
	 * Odpisi vrednosti (22+23+24)
	 */
	public $p144;
	/**
	 * 22 145 1.
	 * Amortizacija
	 */
	public $p145 = 0;
	/**
	 * 23 146 2.
	 * Prevrednotovalni poslovni odhodki pri neopredmetenih sredstvih in opredmetenih osnovnih sredstvih
	 */
	public $p146 = 0;
	/**
	 * 24 147 3.
	 * Prevrednotovalni poslovni odhodki pri obratnih sredstvih
	 */
	public $p147 = 0;
	/**
	 * 25 148 IV.
	 * Drugi poslovni odhodki (26+27)
	 */
	public $p148;
	/**
	 * 26 148a 1.
	 * Prispevki za socialno varnost podjetnika
	 */
	public $p148a = 0;
	/**
	 * 27 148b 2.
	 * Ostali stroški
	 */
	public $p148b = 0;
	/**
	 * 28 151 H.
	 * DOBIČEK IZ POSLOVANJA (10-11)
	 */
	public $p151;
	/**
	 * 29 152 I.
	 * IZGUBA IZ POSLOVANJA (11-10)
	 */
	public $p152;
	/**
	 * 30 153 J.
	 * FINANČNI PRIHODKI (31+32+33)
	 */
	public $p153;
	/**
	 * 31 155 I.
	 * Finančni prihodki iz deležev
	 */
	public $p155 = 0;
	/**
	 * 32 160 II.
	 * Finančni prihodki iz danih posojil
	 */
	public $p160 = 0;
	/**
	 * 33 163 III.
	 * Finančni prihodki iz poslovnih terjatev
	 */
	public $p163 = 0;
	/**
	 * 34 166 K.
	 * FINANČNI ODHODKI (36+37+38)
	 */
	public $p166;
	/**
	 * 35 167 Finančni odhodki za obresti (upoštevano že v II.
	 * in III.)
	 */
	public $p167 = 0;
	/**
	 * 36 168 I.
	 * Finančni odhodki iz oslabitve in odpisov finančnih naložb
	 */
	public $p168 = 0;
	/**
	 * 37 169 II.
	 * Finančni odhodki iz finančnih obveznosti
	 */
	public $p169 = 0;
	/**
	 * 38 174 III.
	 * Finančni odhodki iz poslovnih obveznosti
	 */
	public $p174 = 0;
	/**
	 * 39 178 L.
	 * DRUGI PRIHODKI (40+41)
	 */
	public $p178;
	/**
	 * 40 179 I.
	 * Subvencije, dotacije in podobni prihodki, ki niso povezani s poslovnimi učinki
	 */
	public $p179 = 0;
	/**
	 * 41 180 II.
	 * Drugi finančni prihodki in ostali prihodki
	 */
	public $p180 = 0;
	/**
	 * 42 181 M.
	 * DRUGI ODHODKI
	 */
	public $p181 = 0;
	/**
	 * 43 182 N.
	 * Podjetnikov dohodek (28-29+30-34+39-42)
	 */
	public $p182;
	/**
	 * 44 183 O.
	 * Negativni poslovni izid (29-28-30+34-39+42)
	 */
	public $p183;
	/**
	 * 45 188 *POVPREČNO ŠTEVILO ZAPOSLENIH NA PODLAGI DELOVNIH UR V OBRAČUNSKEM OBDOBJU (na dve decimalki)
	 */
	public $p188 = 0;
	/**
	 * 46 189 ŠTEVILO MESECEV POSLOVANJA
	 */
	public $p189 = 0;
	public function calculate(): void {
		$this->p110 = $this->p111 + $this->p115 + $this->p118;

		$this->p126 = $this->p110 + $this->p121 - $this->p122 + $this->p123 + $this->p124 + $this->p125;

		$this->p128 = $this->p129 + $this->p130 + $this->p134;

		$this->p139 = $this->p140 + $this->p141 + $this->p142 + $this->p143;

		$this->p144 = $this->p145 + $this->p146 + $this->p147;

		$this->p148 = $this->p148a + $this->p148b;

		$this->p127 = $this->p128 + $this->p139 + $this->p144 + $this->p148;

		$interm = $this->p126 - $this->p127;

		$this->p151 = $interm > 0 ? $interm : 0;

		$this->p152 = $interm < 0 ? - $interm : 0;

		$this->p153 = $this->p155 + $this->p160 + $this->p163;

		$this->p166 = $this->p168 + $this->p169 + $this->p174;

		$this->p178 = $this->p179 + $this->p180;

		$interm = $this->p151 - $this->p152 + $this->p153 - $this->p166 + $this->p178 - $this->p181;

		$this->p182 = $interm > 0 ? $interm : 0;

		$this->p183 = $interm < 0 ? - $interm : 0;

		$this->p188 = 0;
		$this->p189 = 12;
	}
}
