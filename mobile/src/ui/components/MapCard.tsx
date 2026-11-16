import React, { useEffect, useState } from 'react';
import { View, Text, Pressable, Linking, Alert, Image, Platform, StyleSheet, ActivityIndicator } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useTheme, spacing, radius, typography } from '../theme';

type Address = {
  line_1?: string;
  line_2?: string;
  county?: string;
  postcode?: string;
};

interface MapCardProps {
  lat?: number | null;
  lng?: number | null;
  address?: Address;
}

/** 
 * Enhanced MapCard with:
 * - Static map preview image (OpenStreetMap)
 * - Full address display
 * - Distance badge (from user's current location)
 * - Multiple navigation options (Google Maps, Apple Maps, Waze)
 * - Copy address action
 */
export default function MapCard({ lat, lng, address }: MapCardProps) {
  const { colors } = useTheme();
  const [distance, setDistance] = useState<string | null>(null);
  const [userCoords, setUserCoords] = useState<{ lat: number; lng: number } | null>(null);
  const [mapLoading, setMapLoading] = useState(true);
  const [mapError, setMapError] = useState(false);

  const hasCoords = typeof lat === 'number' && Number.isFinite(lat) && typeof lng === 'number' && Number.isFinite(lng);
  const addrParts = [address?.line_1, address?.line_2, address?.county, address?.postcode].filter(Boolean);
  const addrStr = addrParts.join(', ');
  const query = hasCoords ? `${lat},${lng}` : addrStr ? encodeURIComponent(addrStr) : '';

  // Static map image from OpenStreetMap tile server
  const mapImageUrl = hasCoords
    ? `https://staticmap.thismoment.dev/staticmap.php?center=${lat},${lng}&zoom=15&size=600x300&maptype=mapnik&markers=${lat},${lng},red-pushpin`
    : null;

  // Fallback to a simple map tile if the above doesn't work
  const fallbackMapUrl = hasCoords
    ? `https://maps.geoapify.com/v1/staticmap?style=osm-bright&width=600&height=300&center=lonlat:${lng},${lat}&zoom=15&marker=lonlat:${lng},${lat};color:%23ff0000;size:medium&apiKey=demo`
    : null;

  // Get user location and calculate distance
  useEffect(() => {
    if (!hasCoords) return;

    if (typeof navigator !== 'undefined' && navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        (position) => {
          const uLat = position.coords.latitude;
          const uLng = position.coords.longitude;
          setUserCoords({ lat: uLat, lng: uLng });
          const dist = calculateDistance(uLat, uLng, lat!, lng!);
          setDistance(formatDistance(dist));
        },
        () => {
          // Silently fail - distance is not critical
        },
        { timeout: 5000 }
      );
    }
  }, [lat, lng, hasCoords]);

  // Haversine formula
  function calculateDistance(lat1: number, lon1: number, lat2: number, lon2: number): number {
    const R = 3958.8; // Earth's radius in miles
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a =
      Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
      Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
  }

  function toRad(deg: number) { return deg * (Math.PI / 180); }

  function formatDistance(miles: number): string {
    if (miles < 0.1) return 'Nearby';
    if (miles < 1) return `${(miles * 5280).toFixed(0)} ft`;
    return `${miles.toFixed(1)} mi`;
  }

  // Navigation URLs
  const googleMapsUrl = query ? `https://www.google.com/maps/dir/?api=1&destination=${query}` : '';
  const appleMapsUrl = hasCoords ? `https://maps.apple.com/?daddr=${lat},${lng}` : addrStr ? `https://maps.apple.com/?daddr=${encodeURIComponent(addrStr)}` : '';
  const wazeUrl = hasCoords ? `https://waze.com/ul?ll=${lat},${lng}&navigate=yes` : '';

  async function openNavigation(url: string, name: string) {
    if (!url) {
      Alert.alert('Location not available', 'No address or coordinates available for this job.');
      return;
    }
    try {
      const canOpen = await Linking.canOpenURL(url);
      if (canOpen) {
        await Linking.openURL(url);
      } else {
        // Fallback to Google Maps web
        if (googleMapsUrl) {
          await Linking.openURL(googleMapsUrl);
        } else {
          Alert.alert('Unable to open', `Could not open ${name}. Please try another option.`);
        }
      }
    } catch {
      Alert.alert('Error', `Failed to open ${name}`);
    }
  }

  function copyAddress() {
    if (!addrStr) return;
    // Use clipboard API for web
    if (typeof navigator !== 'undefined' && navigator.clipboard) {
      navigator.clipboard.writeText(addrStr).then(() => {
        Alert.alert('Copied', 'Address copied to clipboard');
      }).catch(() => {
        Alert.alert('Error', 'Failed to copy address');
      });
    } else {
      Alert.alert('Address', addrStr);
    }
  }

  const styles = createStyles(colors);

  // If no address and no coords, show minimal card
  if (!addrStr && !hasCoords) {
    return (
      <View style={styles.card}>
        <View style={styles.headerRow}>
          <Ionicons name="location-outline" size={20} color={colors.subtext} />
          <Text style={styles.headerTitle}>Location</Text>
        </View>
        <Text style={styles.noLocation}>Location not provided for this job</Text>
      </View>
    );
  }

  return (
    <View style={styles.card}>
      {/* Header */}
      <View style={styles.headerRow}>
        <Ionicons name="location" size={20} color={colors.primary} />
        <Text style={styles.headerTitle}>Location</Text>
        {distance && (
          <View style={styles.distanceBadge}>
            <Ionicons name="navigate-outline" size={12} color={colors.primary} />
            <Text style={styles.distanceText}>{distance}</Text>
          </View>
        )}
      </View>

      {/* Map Preview */}
      {hasCoords && (
        <View style={styles.mapContainer}>
          {mapLoading && !mapError && (
            <View style={styles.mapPlaceholder}>
              <ActivityIndicator size="small" color={colors.primary} />
            </View>
          )}
          {!mapError ? (
            <Pressable onPress={() => openNavigation(googleMapsUrl, 'Google Maps')}>
              <Image
                source={{ uri: mapImageUrl || fallbackMapUrl || '' }}
                style={[styles.mapImage, mapLoading && { height: 0 }]}
                onLoad={() => setMapLoading(false)}
                onError={() => {
                  setMapError(true);
                  setMapLoading(false);
                }}
                resizeMode="cover"
              />
            </Pressable>
          ) : (
            // Fallback: styled map placeholder with coordinates
            <Pressable
              style={styles.mapFallback}
              onPress={() => openNavigation(googleMapsUrl, 'Google Maps')}
            >
              <View style={styles.mapFallbackContent}>
                <Ionicons name="map" size={40} color={colors.primary} />
                <Text style={styles.mapFallbackText}>Tap to open in Maps</Text>
                <Text style={styles.mapFallbackCoords}>
                  {lat!.toFixed(4)}°, {lng!.toFixed(4)}°
                </Text>
              </View>
            </Pressable>
          )}
        </View>
      )}

      {/* Address */}
      {addrStr && (
        <Pressable onPress={copyAddress} style={styles.addressRow}>
          <View style={styles.addressContent}>
            {addrParts.map((part, idx) => (
              <Text
                key={idx}
                style={idx === 0 ? styles.addressPrimary : styles.addressSecondary}
              >
                {part}
              </Text>
            ))}
          </View>
          <Ionicons name="copy-outline" size={16} color={colors.subtext} />
        </Pressable>
      )}

      {/* Navigation Buttons */}
      <View style={styles.navButtonsRow}>
        <Pressable
          style={[styles.navButton, styles.navButtonPrimary]}
          onPress={() => openNavigation(googleMapsUrl, 'Google Maps')}
          disabled={!query}
        >
          <Ionicons name="navigate" size={16} color="#fff" />
          <Text style={styles.navButtonPrimaryText}>Navigate</Text>
        </Pressable>

        <Pressable
          style={[styles.navButton, styles.navButtonSecondary]}
          onPress={() => openNavigation(appleMapsUrl, 'Apple Maps')}
          disabled={!appleMapsUrl}
        >
          <Ionicons name="map-outline" size={16} color={colors.primary} />
          <Text style={[styles.navButtonSecondaryText, { color: colors.primary }]}>Apple</Text>
        </Pressable>

        {hasCoords && (
          <Pressable
            style={[styles.navButton, styles.navButtonSecondary]}
            onPress={() => openNavigation(wazeUrl, 'Waze')}
          >
            <Ionicons name="car-outline" size={16} color={colors.primary} />
            <Text style={[styles.navButtonSecondaryText, { color: colors.primary }]}>Waze</Text>
          </Pressable>
        )}
      </View>
    </View>
  );
}

const createStyles = (colors: any) => StyleSheet.create({
  card: {
    backgroundColor: colors.surface,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.border,
    overflow: 'hidden',
    shadowColor: '#000',
    shadowOpacity: 0.06,
    shadowRadius: 10,
    shadowOffset: { width: 0, height: 4 },
    elevation: 2,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.lg,
    paddingBottom: spacing.sm,
  },
  headerTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.text,
    flex: 1,
  },
  distanceBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: colors.primary + '15',
    paddingHorizontal: spacing.sm,
    paddingVertical: 4,
    borderRadius: radius.sm,
  },
  distanceText: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.primary,
  },
  noLocation: {
    fontSize: 14,
    color: colors.subtext,
    paddingHorizontal: spacing.lg,
    paddingBottom: spacing.lg,
    fontStyle: 'italic',
  },
  mapContainer: {
    marginHorizontal: spacing.md,
    marginVertical: spacing.sm,
    borderRadius: radius.md,
    overflow: 'hidden',
    position: 'relative',
  },
  mapImage: {
    width: '100%',
    height: 160,
    borderRadius: radius.md,
  },
  mapPlaceholder: {
    ...StyleSheet.absoluteFillObject,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.bg,
    borderRadius: radius.md,
    height: 160,
  },
  mapFallback: {
    height: 160,
    backgroundColor: colors.primary + '08',
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.primary + '20',
    borderStyle: 'dashed',
  },
  mapFallbackContent: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: spacing.sm,
  },
  mapFallbackText: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.primary,
  },
  mapFallbackCoords: {
    fontSize: 12,
    color: colors.subtext,
    fontFamily: Platform.OS === 'ios' ? 'Menlo' : 'monospace',
  },
  addressRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.sm,
    gap: spacing.sm,
  },
  addressContent: {
    flex: 1,
  },
  addressPrimary: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.text,
  },
  addressSecondary: {
    fontSize: 13,
    color: colors.subtext,
    marginTop: 2,
  },
  navButtonsRow: {
    flexDirection: 'row',
    gap: spacing.sm,
    paddingHorizontal: spacing.md,
    paddingBottom: spacing.md,
    paddingTop: spacing.xs,
  },
  navButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingVertical: 10,
    borderRadius: radius.md,
  },
  navButtonPrimary: {
    backgroundColor: colors.primary,
  },
  navButtonPrimaryText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#fff',
  },
  navButtonSecondary: {
    backgroundColor: colors.primary + '10',
    borderWidth: 1,
    borderColor: colors.primary + '30',
  },
  navButtonSecondaryText: {
    fontSize: 13,
    fontWeight: '600',
  },
});
