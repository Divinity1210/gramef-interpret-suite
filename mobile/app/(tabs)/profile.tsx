import { View, Text, StyleSheet, ScrollView, Pressable, RefreshControl, Alert, TextInput, Image } from 'react-native';
import { router } from 'expo-router';
import { useState, useEffect } from 'react';
import { colors, spacing, radius } from '../../src/ui/theme';
import { Ionicons } from '@expo/vector-icons';
import { useAuthStore } from '../../src/state/auth';
import { me, updateProfile } from '../../src/api/client';

export default function ProfileScreen() {
  const { user: storeUser, clear, setAuth, token } = useAuthStore();
  const [user, setUser] = useState<any>(storeUser);
  const [loading, setLoading] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [editing, setEditing] = useState<string | null>(null); // section being edited
  const [saving, setSaving] = useState(false);

  // Editable fields
  const [contactNumber, setContactNumber] = useState('');
  const [addressLine1, setAddressLine1] = useState('');
  const [addressLine2, setAddressLine2] = useState('');
  const [county, setCounty] = useState('');
  const [postcode, setPostcode] = useState('');
  const [bankName, setBankName] = useState('');
  const [accountHolder, setAccountHolder] = useState('');
  const [accountNumber, setAccountNumber] = useState('');
  const [sortCode, setSortCode] = useState('');
  const [profileImageError, setProfileImageError] = useState(false);

  useEffect(() => {
    loadProfile();
  }, []);

  const loadProfile = async () => {
    try {
      setLoading(true);
      const response = await me();
      const rawUser = response.data?.data || response.data;
      setUser(rawUser);
      // Sync editable fields
      syncFields(rawUser);
      // Update store with fresh data
      if (token) {
        await setAuth(token, rawUser);
      }
    } catch (error) {
      console.error('Error loading profile:', error);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  const syncFields = (u: any) => {
    const agent = u?.agent;
    setContactNumber(u?.contact_number || agent?.contact_number || '');
    setAddressLine1(u?.address_line_1 || agent?.address_line_1 || '');
    setAddressLine2(u?.address_line_2 || agent?.address_line_2 || '');
    setCounty(u?.county || agent?.county || '');
    setPostcode(u?.postcode || agent?.postcode || '');
    // Bank details are returned at the top level by the API (from bank_accounts table)
    setBankName(u?.bank_name || agent?.bank_name || '');
    setAccountHolder(u?.account_holder || agent?.account_holder || '');
    setAccountNumber(u?.account_number || agent?.account_number || '');
    setSortCode(u?.sort_code || agent?.sort_code || '');
  };

  const handleRefresh = () => {
    setRefreshing(true);
    loadProfile();
  };

  const handleSave = async (section: string) => {
    try {
      setSaving(true);
      const data: any = {};
      if (section === 'contact') {
        data.contact_number = contactNumber;
      } else if (section === 'address') {
        data.address_line_1 = addressLine1;
        data.address_line_2 = addressLine2;
        data.county = county;
        data.postcode = postcode;
      } else if (section === 'banking') {
        data.bank_name = bankName;
        data.account_holder = accountHolder;
        data.account_number = accountNumber;
        data.sort_code = sortCode;
      }
      await updateProfile(data);
      await loadProfile();
      setEditing(null);
      Alert.alert('Success', 'Profile updated successfully');
    } catch (error) {
      console.error('Error updating profile:', error);
      Alert.alert('Error', 'Failed to update profile');
    } finally {
      setSaving(false);
    }
  };

  const handleLogout = () => {
    Alert.alert(
      'Logout',
      'Are you sure you want to logout?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Logout',
          style: 'destructive',
          onPress: async () => {
            await clear();
            router.replace('/login');
          },
        },
      ]
    );
  };

  const agent = user?.agent;
  const roles = (user?.roles || []).map((r: string) => r.toLowerCase());
  const isAgent = roles.some((r: string) => r.includes('agent') || r.includes('interpreter') || r.includes('translator'));
  const roleDisplay = roles.map((r: string) =>
    r.charAt(0).toUpperCase() + r.slice(1).replace('-', ' ')
  ).join(', ');

  const genderDisplay = agent?.gender === 0 ? 'Male' : agent?.gender === 1 ? 'Female' : 'Not specified';

  return (
    <View style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Profile</Text>
      </View>

      <ScrollView
        style={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={handleRefresh} tintColor={colors.primary} />
        }
      >
        {/* Profile Photo & Name Section */}
        <View style={styles.profileSection}>
          <View style={styles.avatarContainer}>
            <View style={styles.avatar}>
              {agent?.profile_picture_url && !profileImageError ? (
                <Image
                  source={{ uri: agent.profile_picture_url }}
                  style={{ width: 94, height: 94, borderRadius: 47 }}
                  resizeMode="cover"
                  onError={() => setProfileImageError(true)}
                />
              ) : (
                <Ionicons name="person" size={48} color={colors.subtext} />
              )}
            </View>
          </View>
          <Text style={styles.name}>{user?.full_name || user?.name || 'User Name'}</Text>
          <Text style={styles.role}>{roleDisplay || 'User'}</Text>
          {agent?.agent_type && (
            <Text style={styles.agentType}>{agent.agent_type}</Text>
          )}
        </View>

        {/* Contact Info */}
        <View style={styles.sectionContainer}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>Contact Information</Text>
            {editing !== 'contact' ? (
              <Pressable onPress={() => setEditing('contact')}>
                <Ionicons name="create-outline" size={20} color={colors.primary} />
              </Pressable>
            ) : (
              <View style={styles.editActions}>
                <Pressable onPress={() => { setEditing(null); syncFields(user); }}>
                  <Text style={styles.cancelText}>Cancel</Text>
                </Pressable>
                <Pressable onPress={() => handleSave('contact')} disabled={saving}>
                  <Text style={styles.saveText}>{saving ? 'Saving...' : 'Save'}</Text>
                </Pressable>
              </View>
            )}
          </View>
          <View style={styles.card}>
            <InfoRow icon="mail-outline" label="Email" value={user?.email || 'N/A'} />
            {editing === 'contact' ? (
              <View style={styles.editField}>
                <Text style={styles.editLabel}>Phone</Text>
                <TextInput
                  style={styles.editInput}
                  value={contactNumber}
                  onChangeText={setContactNumber}
                  placeholder="Enter phone number"
                  placeholderTextColor={colors.subtext}
                  keyboardType="phone-pad"
                />
              </View>
            ) : (
              <InfoRow icon="call-outline" label="Phone" value={contactNumber || 'Not set'} />
            )}
            <InfoRow icon="person-outline" label="Gender" value={genderDisplay} />
            <InfoRow icon="shield-checkmark-outline" label="Account Type" value={isAgent ? 'Agent' : 'Client'} />
          </View>
        </View>

        {/* Address */}
        {isAgent && (
          <View style={styles.sectionContainer}>
            <View style={styles.sectionHeader}>
              <Text style={styles.sectionTitle}>Address</Text>
              {editing !== 'address' ? (
                <Pressable onPress={() => setEditing('address')}>
                  <Ionicons name="create-outline" size={20} color={colors.primary} />
                </Pressable>
              ) : (
                <View style={styles.editActions}>
                  <Pressable onPress={() => { setEditing(null); syncFields(user); }}>
                    <Text style={styles.cancelText}>Cancel</Text>
                  </Pressable>
                  <Pressable onPress={() => handleSave('address')} disabled={saving}>
                    <Text style={styles.saveText}>{saving ? 'Saving...' : 'Save'}</Text>
                  </Pressable>
                </View>
              )}
            </View>
            <View style={styles.card}>
              {editing === 'address' ? (
                <>
                  <EditField label="Address Line 1" value={addressLine1} onChange={setAddressLine1} />
                  <EditField label="Address Line 2" value={addressLine2} onChange={setAddressLine2} />
                  <EditField label="County" value={county} onChange={setCounty} />
                  <EditField label="Postcode" value={postcode} onChange={setPostcode} />
                </>
              ) : (
                <>
                  <InfoRow icon="location-outline" label="Address" value={addressLine1 || 'Not set'} />
                  {addressLine2 ? <InfoRow icon="location-outline" label="" value={addressLine2} /> : null}
                  <InfoRow icon="map-outline" label="County" value={county || 'Not set'} />
                  <InfoRow icon="navigate-outline" label="Postcode" value={postcode || 'Not set'} />
                </>
              )}
            </View>
          </View>
        )}

        {/* Skills & Languages */}
        {isAgent && (
          <View style={styles.sectionContainer}>
            <Text style={styles.sectionTitle}>Skills & Languages</Text>
            <View style={styles.card}>
              <Text style={styles.chipLabel}>Skills</Text>
              <View style={styles.chipContainer}>
                {(agent?.skills || []).length > 0 ? agent.skills.map((s: any) => (
                  <View key={s.id} style={styles.chip}>
                    <Text style={styles.chipText}>{s.name}</Text>
                  </View>
                )) : (
                  <Text style={styles.emptyText}>No skills assigned</Text>
                )}
              </View>
              <Text style={[styles.chipLabel, { marginTop: spacing.md }]}>Languages</Text>
              <View style={styles.chipContainer}>
                {(agent?.languages || []).length > 0 ? agent.languages.map((l: any) => (
                  <View key={l.id} style={[styles.chip, styles.langChip]}>
                    <Text style={[styles.chipText, styles.langChipText]}>{l.name}</Text>
                  </View>
                )) : (
                  <Text style={styles.emptyText}>No languages assigned</Text>
                )}
              </View>
            </View>
          </View>
        )}

        {/* Banking Info */}
        {isAgent && (
          <View style={styles.sectionContainer}>
            <View style={styles.sectionHeader}>
              <Text style={styles.sectionTitle}>Banking Details</Text>
              {editing !== 'banking' ? (
                <Pressable onPress={() => setEditing('banking')}>
                  <Ionicons name="create-outline" size={20} color={colors.primary} />
                </Pressable>
              ) : (
                <View style={styles.editActions}>
                  <Pressable onPress={() => { setEditing(null); syncFields(user); }}>
                    <Text style={styles.cancelText}>Cancel</Text>
                  </Pressable>
                  <Pressable onPress={() => handleSave('banking')} disabled={saving}>
                    <Text style={styles.saveText}>{saving ? 'Saving...' : 'Save'}</Text>
                  </Pressable>
                </View>
              )}
            </View>
            <View style={styles.card}>
              {editing === 'banking' ? (
                <>
                  <EditField label="Bank Name" value={bankName} onChange={setBankName} />
                  <EditField label="Account Holder" value={accountHolder} onChange={setAccountHolder} />
                  <EditField label="Account Number" value={accountNumber} onChange={setAccountNumber} keyboardType="numeric" />
                  <EditField label="Sort Code" value={sortCode} onChange={setSortCode} />
                </>
              ) : (
                <>
                  <InfoRow icon="business-outline" label="Bank" value={bankName || 'Not set'} />
                  <InfoRow icon="person-outline" label="Holder" value={accountHolder || 'Not set'} />
                  <InfoRow icon="card-outline" label="Account" value={accountNumber ? `****${accountNumber.slice(-4)}` : 'Not set'} />
                  <InfoRow icon="keypad-outline" label="Sort Code" value={sortCode || 'Not set'} />
                </>
              )}
            </View>
          </View>
        )}

        {/* Preferences */}
        {isAgent && (
          <View style={styles.sectionContainer}>
            <Text style={styles.sectionTitle}>Preferences</Text>
            <View style={styles.card}>
              <InfoRow icon="document-outline" label="Affidavit" value={agent?.can_provide_affidavit ? 'Yes' : 'No'} />
              <InfoRow icon="document-text-outline" label="Affirmation" value={agent?.can_provide_affirmation ? 'Yes' : 'No'} />
              {agent?.dbs_number && <InfoRow icon="shield-outline" label="DBS Number" value={agent.dbs_number} />}
              {agent?.dbs_expiry_date && <InfoRow icon="calendar-outline" label="DBS Expiry" value={agent.dbs_expiry_date} />}
              {agent?.induction_date && <InfoRow icon="school-outline" label="Induction Date" value={agent.induction_date} />}
            </View>
          </View>
        )}

        {/* Action Buttons */}
        <View style={styles.actionsSection}>
          <Pressable style={[styles.actionButton, styles.logoutButton]} onPress={handleLogout}>
            <Ionicons name="log-out-outline" size={22} color={colors.danger} />
            <Text style={[styles.actionButtonText, styles.logoutText]}>Logout</Text>
            <Ionicons name="chevron-forward" size={20} color={colors.subtext} />
          </Pressable>
        </View>
      </ScrollView>
    </View>
  );
}

// Reusable info row
function InfoRow({ icon, label, value }: { icon: string; label: string; value: string }) {
  return (
    <View style={styles.infoRow}>
      <Ionicons name={icon as any} size={18} color={colors.primary} />
      <View style={styles.infoContent}>
        {label ? <Text style={styles.infoLabel}>{label}</Text> : null}
        <Text style={styles.infoValue}>{value}</Text>
      </View>
    </View>
  );
}

// Editable field
function EditField({ label, value, onChange, keyboardType }: { label: string; value: string; onChange: (v: string) => void; keyboardType?: any }) {
  return (
    <View style={styles.editField}>
      <Text style={styles.editLabel}>{label}</Text>
      <TextInput
        style={styles.editInput}
        value={value}
        onChangeText={onChange}
        placeholder={`Enter ${label.toLowerCase()}`}
        placeholderTextColor={colors.subtext}
        keyboardType={keyboardType || 'default'}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.bg,
  },
  header: {
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.xl,
    paddingBottom: spacing.md,
    backgroundColor: colors.surface,
  },
  headerTitle: {
    fontSize: 28,
    fontWeight: '700',
    color: colors.text,
  },
  content: {
    flex: 1,
  },
  profileSection: {
    alignItems: 'center',
    paddingVertical: spacing.xl,
    backgroundColor: colors.surface,
  },
  avatarContainer: {
    marginBottom: spacing.md,
  },
  avatar: {
    width: 100,
    height: 100,
    borderRadius: 50,
    backgroundColor: colors.bg,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 3,
    borderColor: colors.primary,
  },
  name: {
    fontSize: 24,
    fontWeight: '700',
    color: colors.text,
    marginBottom: spacing.xs,
  },
  role: {
    fontSize: 16,
    color: colors.subtext,
  },
  agentType: {
    fontSize: 13,
    color: colors.primary,
    marginTop: 4,
    fontWeight: '500',
  },
  sectionContainer: {
    padding: spacing.lg,
    paddingBottom: 0,
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: spacing.sm,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.text,
    marginBottom: spacing.sm,
  },
  card: {
    backgroundColor: colors.surface,
    borderRadius: radius.lg,
    padding: spacing.md,
    gap: spacing.sm,
  },
  infoRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    paddingVertical: 4,
  },
  infoContent: {
    flex: 1,
  },
  infoLabel: {
    fontSize: 11,
    color: colors.subtext,
    marginBottom: 2,
  },
  infoValue: {
    fontSize: 15,
    color: colors.text,
    fontWeight: '500',
  },
  chipLabel: {
    fontSize: 13,
    fontWeight: '600',
    color: colors.subtext,
    marginBottom: 6,
  },
  chipContainer: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
  },
  chip: {
    backgroundColor: colors.primary + '20',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
  },
  chipText: {
    fontSize: 12,
    color: colors.primary,
    fontWeight: '500',
  },
  langChip: {
    backgroundColor: '#22c55e20',
  },
  langChipText: {
    color: '#22c55e',
  },
  emptyText: {
    fontSize: 13,
    color: colors.subtext,
    fontStyle: 'italic',
  },
  editField: {
    marginBottom: spacing.sm,
  },
  editLabel: {
    fontSize: 12,
    color: colors.subtext,
    marginBottom: 4,
  },
  editInput: {
    backgroundColor: colors.bg,
    borderRadius: radius.md,
    padding: spacing.sm,
    fontSize: 15,
    color: colors.text,
    borderWidth: 1,
    borderColor: colors.border,
  },
  editActions: {
    flexDirection: 'row',
    gap: spacing.md,
  },
  cancelText: {
    fontSize: 14,
    color: colors.subtext,
    fontWeight: '500',
  },
  saveText: {
    fontSize: 14,
    color: colors.primary,
    fontWeight: '600',
  },
  actionsSection: {
    padding: spacing.lg,
    gap: spacing.sm,
  },
  actionButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.surface,
    padding: spacing.md,
    borderRadius: radius.lg,
    gap: spacing.md,
  },
  actionButtonText: {
    flex: 1,
    fontSize: 16,
    fontWeight: '500',
    color: colors.text,
  },
  logoutButton: {
    marginTop: spacing.md,
  },
  logoutText: {
    color: colors.danger,
  },
});
